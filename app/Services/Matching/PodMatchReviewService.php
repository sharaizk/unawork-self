<?php

namespace App\Services\Matching;

use App\Contracts\PodMatchingAlgorithm;
use App\Enums\PodUser\State;
use App\Helpers\FileHelper;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodMatchReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read model of a POD's business match-review queue (UNA-018). Reads only.
 *
 * A review is listed when it is unresolved, its membership belongs to this POD, is still
 * pending_business_approval and is not the owner's, and the employee still exists. Origin comes
 * from the pod_match_reviews row itself (never from the state, a match or a history reason), so
 * manual applications never appear.
 *
 * Each item shows the employee's CURRENT best match for the POD (active algorithm + version, active
 * requirement, best by DeterministicPodMatchingService::rank()) and its UNA-014 qualification.
 * Ranking: qualified first, then score desc, then review creation asc, then review id asc.
 */
class PodMatchReviewService
{
    public function __construct(private PodMatchPresenter $presenter)
    {
    }

    /** @return Collection<int, PodMatchReviewItem> every pending review of the POD, ranked (light: no reasons) */
    public function ranked(Pod $pod): Collection
    {
        $reviews = PodMatchReview::query()->unresolved()
            ->whereHas('membership', fn ($q) => $q->where('pod_id', $pod->id)
                ->where('state', State::PENDING_BUSINESS_APPROVAL->value)
                ->where('permission', '!=', 'Owner'))
            ->with(['membership.user.profile.jobTitle'])
            ->get()
            ->filter(fn (PodMatchReview $r) => $r->membership?->user !== null);

        $best = $this->bestMatches($pod, $reviews->map(fn ($r) => (int) $r->membership->user_id)->all());

        return $reviews
            ->map(fn (PodMatchReview $r) => $this->item($pod, $r, $best[(int) $r->membership->user_id] ?? null, false))
            ->sort(fn (PodMatchReviewItem $a, PodMatchReviewItem $b) => [
                !$a->isQualified(), -$a->scoreHundredths(), $a->review->created_at->getTimestamp(), $a->review->id,
            ] <=> [
                !$b->isQualified(), -$b->scoreHundredths(), $b->review->created_at->getTimestamp(), $b->review->id,
            ])
            ->values();
    }

    /** @return LengthAwarePaginator<PodMatchReviewItem> one page with explanations loaded */
    public function paginate(Pod $pod, int $page = 1, ?int $perPage = null, ?Collection $ranked = null): LengthAwarePaginator
    {
        $perPage ??= max(1, (int) config('pod_matching.reviews.per_page', 10));
        $ranked ??= $this->ranked($pod);
        $slice = $ranked->forPage($page, $perPage)->values();

        $matches = new EloquentCollection($slice->map->match->filter()->values()->all());
        $matches->loadMissing(['reasons', 'requirement.jobTitle']);
        $items = $slice->map(fn (PodMatchReviewItem $i) => $this->item($pod, $i->review, $i->match, true));

        return new LengthAwarePaginator($items, $ranked->count(), $perPage, $page, [
            'path' => route('pods.match-reviews.index', $pod),
            'pageName' => 'page',
        ]);
    }

    /** @return array{pending:int, top_score:?string, qualified:int, stale:int} accurate, from the ranked queue */
    public function summary(Collection $ranked): array
    {
        $qualified = $ranked->filter->isQualified()->count();
        $top = $ranked->map->match->filter()->sortByDesc(fn (PodMatch $m) => MatchQualification::hundredths($m->score))->first();

        return [
            'pending' => $ranked->count(),
            'top_score' => $top ? (string) $top->score : null,
            'qualified' => $qualified,
            'stale' => $ranked->count() - $qualified,
        ];
    }

    /**
     * The employee's current best match for the POD with reasons and requirement loaded, or null.
     * Used by PodMatchReviewDecisionService to re-validate an approval.
     */
    public function bestMatchFor(Pod $pod, int $userId): ?PodMatch
    {
        $match = $this->bestMatches($pod, [$userId])[$userId] ?? null;
        $match?->loadMissing(['reasons', 'requirement.jobTitle']);

        return $match;
    }

    // ------------------------------------------------------------------ internals

    /** @return array<int, PodMatch> user id => best current match (requirement + pod set) */
    private function bestMatches(Pod $pod, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $algorithm = app(PodMatchingAlgorithm::class);

        return PodMatch::query()
            ->where('pod_id', $pod->id)
            ->whereIn('user_id', array_values(array_unique($userIds)))
            ->where('algorithm', $algorithm->key())
            ->where('scoring_version', $algorithm->version())
            ->whereHas('requirement', fn ($q) => $q->where('is_active', true))
            ->with('requirement')
            ->get()
            ->each(function (PodMatch $m) use ($pod) {
                $m->setRelation('pod', $pod);
                $m->requirement->setRelation('pod', $pod);
            })
            ->groupBy('user_id')
            ->map(fn ($matches) => $matches->sort(fn ($a, $b) => DeterministicPodMatchingService::rank($a) <=> DeterministicPodMatchingService::rank($b))->first())
            ->all();
    }

    private function item(Pod $pod, PodMatchReview $review, ?PodMatch $match, bool $withExplanation): PodMatchReviewItem
    {
        $employee = $review->membership->user;
        $profile = $employee->profile;
        $presented = $withExplanation && $match !== null ? $this->presenter->present($match) : null;

        return new PodMatchReviewItem(
            review: $review,
            employee: $employee,
            match: $match,
            qualification: $match ? MatchQualification::of($match, $match->requirement, $pod) : null,
            presented: $presented,
            highlights: $presented ? $this->presenter->topHighlights($presented['criteria']) : [],
            headline: $profile?->jobTitle?->title,
            profile: $this->profileFacts($profile),
            avatarUrl: $employee->avatar ? FileHelper::getUrl('users-avatar/' . $employee->avatar, 'small') : null,
        );
    }

    /** Business-relevant facts only: no address, phone, postal code, e-mail or links. */
    private function profileFacts($profile): array
    {
        if ($profile === null || !$profile->exists) {
            return [];
        }
        $facts = [];
        if ($profile->years_experience !== null) {
            $facts['experience'] = __(':years years of experience', ['years' => (string) (float) $profile->years_experience]);
        }
        $place = collect([$profile->city, $profile->country])->filter(fn ($v) => trim((string) $v) !== '')->implode(', ');
        if ($place !== '') {
            $facts['location'] = $place;
        }
        if ($profile->availability_status) {
            $facts['availability'] = $profile->availability_status->label();
        }

        return $facts;
    }

}
