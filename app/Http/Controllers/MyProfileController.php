<?php

namespace App\Http\Controllers;

use App\Enums\Profile\AvailabilityStatus;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\ProjectType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use App\Http\Requests\Profile\UpdateMyProfileRequest;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Profile;
use App\Models\Skill;
use App\Services\Profile\ProfileCompletionService;
use App\Services\ProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The employee's own profile editor at /my-profile (UNA-007): onboarding and later edits.
 * Persistence lives in ProfileService and scoring in ProfileCompletionService.
 */
class MyProfileController extends Controller
{
    public function __construct(
        private ProfileService $profiles,
        private ProfileCompletionService $completion,
    ) {
    }

    public function edit(Request $request)
    {
        $this->authorize('editOwn', Profile::class);

        $user = $request->user();
        $user->load(array_merge(ProfileCompletionService::RELATIONS, ['profile.jobTitle', 'profile.industry', 'profile.preferredRoles.jobTitle']));
        $profile = $user->profile;
        $breakdown = $this->completion->breakdown($user);

        return view('employee.my_profile', [
            'user' => $user,
            'profile' => $profile,
            'breakdown' => $breakdown,
            'industries' => Industry::query()->alphabetical()->get(['id', 'name']),
            // Industry tags drive the client-side picker filtering (data-industries on each option).
            'jobTitles' => JobTitle::query()->with('industries:id')->alphabetical()->get(['id', 'title']),
            'skills' => Skill::query()->with('industries:id')->alphabetical()->get(['id', 'name']),
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::ALL),
            'proficiencies' => SkillProficiency::options(),
            'availabilityOptions' => AvailabilityStatus::options(),
            'workModes' => WorkMode::options(),
            'engagementTypes' => EngagementType::options(),
            'projectTypes' => ProjectType::options(),
        ]);
    }

    public function update(UpdateMyProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->profiles->saveProfile($user, $request->validated(), $request->file('cv_file'));
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withInput($request->except('cv_file'))
                ->with('error', __('Your profile could not be saved. Please try again.'));
        }

        $percentage = $this->completion->percentage($user->fresh());
        $required = $this->completion->required();
        $message = $percentage >= $required
            ? __('Profile saved. Your profile is :percentage% complete.', ['percentage' => $percentage])
            : __('Draft saved. Your profile is :percentage% complete; :required% is required to access jobs and active POD circles.', ['percentage' => $percentage, 'required' => $required]);

        return redirect()->route('my-profile.edit')->with('success', $message);
    }
}
