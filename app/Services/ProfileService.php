<?php

namespace App\Services;

use App\Enums\Profile\AvailabilityStatus;
use App\Models\Profile;
use App\Models\ProfileCertification;
use App\Models\ProfileEducation;
use App\Models\ProfileExperience;
use App\Models\ProfilePortfolio;
use App\Models\ProfilePreferredRole;
use App\Models\ProfileProjectType;
use App\Models\User;
use App\Services\Matching\EmployeeMatchingFingerprint;
use App\Services\Matching\MatchGenerationDispatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Employee profile persistence.
 *
 * saveProfile() (UNA-007) writes the profile and every nested record in one transaction. A
 * replacement CV is uploaded first; the previous file is deleted only after the commit, and the new
 * upload is removed again if the transaction fails. The owner always comes from the authenticated
 * user; user_id / profile_id are never taken from input, and nested ids must belong to the profile.
 */
class ProfileService
{
    public const CV_DISK = 'public';
    private const ATTEMPTS = 3;

    public function __construct(
        private ?EmployeeMatchingFingerprint $matchingFingerprint = null,
        private ?MatchGenerationDispatcher $matchGeneration = null,
    ) {
        $this->matchingFingerprint ??= app(EmployeeMatchingFingerprint::class);
        $this->matchGeneration ??= app(MatchGenerationDispatcher::class);
    }

    /** Scalar profile columns an employee may edit. */
    public const PROFILE_FIELDS = [
        'about', 'industry_id', 'job_title_id', 'years_experience',
        'country', 'state', 'city', 'zip_code', 'address', 'phone_number', 'timezone',
        'availability_status', 'available_from', 'hours_per_week',
        'preferred_work_mode', 'preferred_engagement_type', 'open_to_relocation', 'willing_to_travel',
        'linkedin_url', 'github_url', 'website',
    ];

    /** Repeatable sections: input key => [model, editable fields]. */
    public const RECORD_SECTIONS = [
        'experiences' => [ProfileExperience::class, ['employer', 'job_title', 'job_title_id', 'engagement_type', 'location', 'start_date', 'end_date', 'is_current', 'description']],
        'educations' => [ProfileEducation::class, ['institution', 'degree', 'field_of_study', 'start_year', 'end_year', 'description']],
        'certifications' => [ProfileCertification::class, ['name', 'issuing_organization', 'issue_date', 'expiration_date', 'credential_id', 'credential_url']],
        'portfolios' => [ProfilePortfolio::class, ['title', 'url', 'description']],
    ];

    /** Every collection the profile form can synchronize (see UpdateMyProfileRequest `sync`). */
    public const SYNCABLE = ['skills', 'experiences', 'educations', 'certifications', 'portfolios', 'preferred_roles', 'project_types'];

    /**
     * Create or update the user's profile and the nested collections named in $data['sync'].
     * A collection not named in `sync` is left untouched; a named one is replaced by the submitted
     * rows (existing rows are updated by id, missing ones deleted, new ones created).
     */
    public function saveProfile(User $user, array $data, ?UploadedFile $cv = null): Profile
    {
        $sync = array_values(array_intersect($data['sync'] ?? [], self::SYNCABLE));

        $profile = $this->withCvReplacement($user, $cv, function (?string $newCv) use ($user, $data, $sync) {
            $profile = Profile::where('user_id', $user->id)->lockForUpdate()->first() ?? new Profile();
            $previousCv = $profile->cv_file;

            $profile->forceFill(['user_id' => $user->id]);
            $profile->fill(Arr::only($data, self::PROFILE_FIELDS));
            if (!($profile->availability_status instanceof AvailabilityStatus && $profile->availability_status->requiresDate())) {
                $profile->available_from = null;
            }
            if ($newCv !== null) {
                $profile->cv_file = $newCv;
            }
            $profile->save();

            foreach (self::RECORD_SECTIONS as $key => [$model, $fields]) {
                if (in_array($key, $sync, true)) {
                    $this->syncRecords($profile, $model, $data[$key] ?? [], $fields);
                }
            }
            if (in_array('preferred_roles', $sync, true)) {
                $this->syncValues($profile, ProfilePreferredRole::class, 'job_title_id', $data['preferred_role_ids'] ?? []);
            }
            if (in_array('project_types', $sync, true)) {
                $this->syncValues($profile, ProfileProjectType::class, 'project_type', $data['project_types'] ?? []);
            }
            if (in_array('skills', $sync, true)) {
                $this->syncSkills($user, $data['skills'] ?? []);
            }

            return [$profile, $previousCv];
        });

        return $profile->fresh(Profile::DETAIL_RELATIONS);
    }

    // ------------------------------------------------------------------ legacy API (api/profile)

    public function createProfile(User $user, array $data): Profile
    {
        if ($user->profile()->exists()) {
            throw new \Exception('User can\t have more than one profile.');
        }

        return $this->updateLegacy($user, $data);
    }

    public function updateProfile(Profile $profile, array $data): Profile
    {
        return $this->updateLegacy($profile->user ?? User::findOrFail($profile->user_id), $data);
    }

    /** Legacy payload (flat fields + skill_ids): same transaction and CV safety as saveProfile(). */
    private function updateLegacy(User $user, array $data): Profile
    {
        $cv = $data['cv_file'] ?? null;
        unset($data['cv_file'], $data['user_id'], $data['profile_id']);

        return $this->withCvReplacement($user, $cv instanceof UploadedFile ? $cv : null, function (?string $newCv) use ($user, $data) {
            $profile = Profile::where('user_id', $user->id)->lockForUpdate()->first() ?? new Profile();
            $previousCv = $profile->cv_file;
            $profile->forceFill(['user_id' => $user->id]);
            $profile->fill(Arr::only($data, ['country', 'state', 'city', 'zip_code', 'address', 'phone_number', 'about', 'job_title_id']));
            if ($newCv !== null) {
                $profile->cv_file = $newCv;
            }
            $profile->save();

            if (isset($data['skill_ids'])) {
                // Plain ids keep the proficiency of skills that stay attached.
                $user->skills()->sync($data['skill_ids']);
            }

            return [$profile, $previousCv];
        });
    }

    // ------------------------------------------------------------------ internals

    /**
     * Run $write in a transaction with an optional, already uploaded replacement CV. $write returns
     * [Profile, previous cv path]. The new file is removed if the transaction fails; the previous
     * file is removed only after a successful commit.
     *
     * UNA-016: the employee's matching fingerprint is taken before and after $write inside the same
     * transaction; when it changed, GenerateMatchesForEmployee is queued after commit (never on
     * rollback). Matching never runs here and never changes onboarding state.
     */
    private function withCvReplacement(User $user, ?UploadedFile $cv, \Closure $write): Profile
    {
        $newCv = $cv ? $this->uploadFile($cv) : null;

        try {
            [$profile, $previousCv] = DB::transaction(function () use ($user, $write, $newCv) {
                $before = $this->matchingFingerprint->of($user->id);
                $written = $write($newCv);
                if ($this->matchingFingerprint->of($user->id) !== $before) {
                    $this->matchGeneration->employeeAfterCommit((int) $user->id);
                }

                return $written;
            }, self::ATTEMPTS);
        } catch (\Throwable $e) {
            if ($newCv !== null) {
                Storage::disk(self::CV_DISK)->delete($newCv);
            }
            throw $e;
        }

        if ($newCv !== null && $previousCv && $previousCv !== $newCv) {
            Storage::disk(self::CV_DISK)->delete($previousCv);
        }

        return $profile;
    }

    /** @param class-string<Model> $model */
    private function syncRecords(Profile $profile, string $model, array $rows, array $fields): void
    {
        $existing = $model::where('profile_id', $profile->id)->lockForUpdate()->get()->keyBy('id');
        $keep = [];

        foreach ($rows as $row) {
            $attributes = Arr::only($row, $fields);
            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;

            if ($id !== null) {
                // The FormRequest already rejects foreign ids; this is the last line of defence.
                $record = $existing->get($id) ?? throw new AuthorizationException(__('This record does not belong to your profile.'));
                $record->fill($attributes)->save();
                $keep[] = $id;
            } else {
                $record = new $model($attributes);
                $record->forceFill(['profile_id' => $profile->id])->save();
                $keep[] = $record->id;
            }
        }

        $model::where('profile_id', $profile->id)->whereNotIn('id', $keep)->delete();
    }

    /** Replace a set of unique values (preferred roles, project types). @param class-string<Model> $model */
    private function syncValues(Profile $profile, string $model, string $column, array $values): void
    {
        $values = array_values(array_unique(array_map('strval', $values)));
        $model::where('profile_id', $profile->id)->whereNotIn($column, $values)->delete();
        $present = $model::where('profile_id', $profile->id)->pluck($column)
            ->map(fn ($v) => $v instanceof \BackedEnum ? (string) $v->value : (string) $v)->all();

        foreach (array_diff($values, $present) as $value) {
            (new $model([$column => $value]))->forceFill(['profile_id' => $profile->id])->save();
        }
    }

    /** @param array<int, array{skill_id:int, proficiency:?string}> $skills */
    private function syncSkills(User $user, array $skills): void
    {
        $pivot = [];
        foreach ($skills as $skill) {
            $pivot[(int) $skill['skill_id']] = ['proficiency' => $skill['proficiency'] ?? null];
        }
        $user->skills()->sync($pivot);
        $user->unsetRelation('skills');
    }

    protected function uploadFile(UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'pdf');
        $path = $file->storeAs('profile-files', \Illuminate\Support\Str::uuid() . '.' . $extension, self::CV_DISK);

        if (!$path) {
            throw new \Exception('Failed to upload file.');
        }

        return $path;
    }
}
