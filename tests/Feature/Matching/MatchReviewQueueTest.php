<?php

namespace Tests\Feature\Matching;

use App\Enums\PodUser\State;
use App\Enums\UserWorkspace\Permission;
use App\Models\Client;
use App\Models\PodMatch;
use App\Models\PodMatchReview;
use App\Models\PodUser;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Services\Matching\PodMatchReviewItem;
use App\Services\Matching\PodMatchReviewService;
use App\Services\Membership\PodStateTransitionService;
use App\Services\PodUserService;
use Illuminate\Support\Facades\DB;

/** UNA-018: queue selection/ranking, HTTP decisions, authorization and the page. In-memory SQLite only. */
class MatchReviewQueueTest extends WorkflowTestCase
{
    private function reviews(): PodMatchReviewService
    {
        return app(PodMatchReviewService::class);
    }

    private function propose(User $candidate, $requirement, string $at = '2026-06-15 12:00:00'): PodMatchReview
    {
        $this->workflow()->evaluateAndApply($requirement->fresh(), $candidate->fresh(), \Carbon\CarbonImmutable::parse($at, 'UTC'));

        return PodMatchReview::whereHas('membership', fn ($q) => $q->where('user_id', $candidate->id)->where('pod_id', $requirement->pod_id))->sole();
    }

    private function manager(): User
    {
        $manager = $this->qualifiedEmployee();
        app(PodStateTransitionService::class)->enter($this->pod, $manager, State::OUTER_CIRCLE, $this->company);
        UserWorkspace::where('workspace_id', $this->pod->workspace->id)->where('user_id', $manager->id)->sole()->givePermissionTo(Permission::USERS_EDIT->value);

        return $manager->fresh();
    }

    /** Three proposals: two qualified (100, created in order) and one that became stale (68.75 after the threshold rose to 90). */
    private function queueFixture(): array
    {
        $this->configurePod('pending_approval', 60);
        $perfect = $this->perfectRequirement();
        $partial = $this->partialRequirement();
        $stale = $this->qualifiedEmployee();
        $staleReview = $this->propose($stale, $partial);                          // 68.75 (best of this POD for them is... see below)
        DB::table('pod_matches')->where('user_id', $stale->id)->where('pod_worker_requirement_id', '!=', $partial->id)->delete();
        $first = $this->qualifiedEmployee();
        $firstReview = $this->propose($first, $perfect);
        $second = $this->qualifiedEmployee();
        $secondReview = $this->propose($second, $perfect);
        $this->configurePod('pending_approval', 90);
        DB::table('pod_match_reviews')->where('id', $firstReview->id)->update(['created_at' => '2026-06-01 10:00:00']);
        DB::table('pod_match_reviews')->where('id', $secondReview->id)->update(['created_at' => '2026-06-02 10:00:00']);

        return compact('stale', 'staleReview', 'first', 'firstReview', 'second', 'secondReview', 'perfect', 'partial');
    }

    // ------------------------------------------------------------------ selection and ranking

    public function test_only_unresolved_matching_proposals_of_this_pod_are_queued_and_ranked(): void
    {
        $f = $this->queueFixture();

        // Noise: a manual application, the owner, another company's POD with its own proposal.
        $manual = $this->qualifiedEmployee();
        app(PodUserService::class)->requestJoinPod($manual->id, $this->pod->id);
        $otherCompany = $this->account('company');
        $otherPod = $this->pods()->createDraft($otherCompany, ['title' => 'Elsewhere']);
        $otherPod->forceFill(['status' => 'published', 'published_at' => now(), 'matching_mode' => 'pending_approval', 'minimum_match_threshold' => 0])->save();
        $otherRequirement = new \App\Models\PodWorkerRequirement(['is_active' => true, 'headcount' => 1, 'job_title_id' => $this->engineer->id]);
        $otherRequirement->forceFill(['pod_id' => $otherPod->id])->save();
        $otherRequirement->skills()->sync([$this->php->id]);
        $this->propose($this->qualifiedEmployee(), $otherRequirement);

        $ranked = $this->reviews()->ranked($this->pod->fresh());
        $this->assertSame([$f['firstReview']->id, $f['secondReview']->id, $f['staleReview']->id], $ranked->map(fn ($i) => $i->review->id)->all(), 'qualified first, then score, then oldest');
        $this->assertSame([null, null, PodMatchReviewItem::STALE_BELOW_THRESHOLD], $ranked->map->staleReason()->all());
        $this->assertSame($ranked->map(fn ($i) => $i->review->id)->all(), $this->reviews()->ranked($this->pod->fresh())->map(fn ($i) => $i->review->id)->all(), 'stable');
        $this->assertSame(['pending' => 3, 'top_score' => '100.00', 'qualified' => 2, 'stale' => 1], $this->reviews()->summary($ranked));

        // Unavailable explanation: no current match left.
        DB::table('pod_matches')->where('user_id', $f['stale']->id)->delete();
        $item = $this->reviews()->ranked($this->pod->fresh())->last();
        $this->assertSame([PodMatchReviewItem::STALE_UNAVAILABLE, null], [$item->staleReason(), $item->match]);
    }

    public function test_the_best_requirement_match_is_shown_with_presenter_reasons_and_pages_without_n_plus_one(): void
    {
        config(['pod_matching.reviews.per_page' => 2]);
        $this->configurePod('pending_approval', 60);
        $partial = $this->partialRequirement();
        $perfect = $this->perfectRequirement();
        $user = $this->qualifiedEmployee();
        $this->propose($user, $partial);
        $this->propose($user, $perfect); // second requirement for the same membership: still one review
        foreach (range(1, 3) as $i) {
            $this->propose($this->qualifiedEmployee(), $perfect);
        }
        $this->assertSame(4, PodMatchReview::count());

        $page = $this->reviews()->paginate($this->pod->fresh(), 1);
        $first = $page->items()[0];
        $this->assertSame([4, 2], [$page->total(), $page->count()]);
        $mine = collect($this->reviews()->paginate($this->pod->fresh(), 1, 10)->items())->first(fn ($i) => $i->employee->id === $user->id);
        $this->assertSame($perfect->id, (int) $mine->match->pod_worker_requirement_id, 'best of the employee\'s requirement matches');
        $this->assertTrue($first->match->relationLoaded('reasons'));
        $this->assertSame('All required skills matched', $first->highlights[0]['highlight']);
        $this->assertSame(9, count($first->presented['criteria']));

        $count = function (int $perPage) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->reviews()->paginate($this->pod->fresh(), 1, $perPage);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $this->assertSame($count(1), $count(4));
    }

    // ------------------------------------------------------------------ HTTP decisions

    public function test_decisions_over_http_return_the_right_status_codes(): void
    {
        $f = $this->queueFixture();
        $url = fn ($review, $pod = null) => route('pods.match-reviews.decision', [$pod ?? $this->pod, $review]);

        $this->actingAs($this->company)->postJson($url($f['staleReview']), ['action' => 'approve'])->assertStatus(422)->assertJsonPath('status', 'not_approvable');
        $this->actingAs($this->company)->postJson($url($f['firstReview']), ['action' => 'maybe'])->assertStatus(422)->assertJsonValidationErrors('action');
        $this->actingAs($this->company)->postJson($url($f['firstReview']), ['action' => 'reject', 'reason' => str_repeat('x', 1001)])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->actingAs($this->company)->postJson($url($f['firstReview']), ['action' => 'approve', 'user_id' => 999, 'state' => 'inner_circle', 'score' => 100])
            ->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('user_id', $f['first']->id)->sole()->state, 'never Inner Circle, input ids ignored');
        $this->actingAs($this->company)->postJson($url($f['firstReview']), ['action' => 'approve'])->assertStatus(409);

        $manager = $this->manager();
        $this->actingAs($manager)->postJson($url($f['secondReview']), ['action' => 'reject'])->assertOk();
        $this->assertSame((int) $manager->id, (int) DB::table('pod_user_state_histories')->orderByDesc('id')->value('actor_id'));

        // Another POD in the URL: not found. Another company: forbidden. Plain form post: redirect + flash.
        $otherPod = $this->pods()->createDraft($this->company, ['title' => 'Mine too']);
        $this->actingAs($this->company)->postJson($url($f['staleReview'], $otherPod), ['action' => 'reject'])->assertNotFound();
        $this->actingAs($this->account('company'))->postJson($url($f['staleReview']), ['action' => 'reject'])->assertForbidden();
        $this->actingAs($this->company)->post($url($f['staleReview']), ['action' => 'exclude'])
            ->assertRedirect(route('pods.match-reviews.index', $this->pod))->assertSessionHas('success');
        $this->assertSame(0, PodMatchReview::unresolved()->count());
    }

    // ------------------------------------------------------------------ access and page

    public function test_only_pod_managers_can_open_the_queue(): void
    {
        $this->configurePod('pending_approval');
        $url = route('pods.match-reviews.index', $this->pod);

        $this->actingAs($this->company)->get($url)->assertOk();
        $this->actingAs($this->manager())->get($url)->assertOk();

        $member = $this->qualifiedEmployee();
        app(PodStateTransitionService::class)->enter($this->pod, $member, State::OUTER_CIRCLE, $this->company);
        $this->actingAs($member)->get($url)->assertForbidden();
        $this->actingAs($this->account('company'))->get($url)->assertForbidden();
        $this->actingAs($this->qualifiedEmployee())->get($url)->assertForbidden();
        $this->actingAs($this->account('administrator'))->get($url)->assertForbidden();
        $client = Client::forceCreate(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'x']);
        $this->actingAs($client, 'client')->get($url)->assertRedirect()->assertDontSee('Match review queue');
    }

    public function test_the_page_renders_cards_explanations_controls_and_no_private_data(): void
    {
        $f = $this->queueFixture();
        $f['first']->forceFill(['name' => 'Anneliese Montgomery-Vanderbilt the Third'])->save();
        $f['first']->profile->forceFill(['phone_number' => '+94 77 123 4567', 'address' => '12 Secret Lane', 'zip_code' => '00999'])->save();

        $page = $this->actingAs($this->company)->get(route('pods.match-reviews.index', $this->pod))->assertOk();

        $page->assertSeeInOrder(['Match review queue', 'Anneliese Montgomery-Vanderbilt the Third', $f['second']->name, $f['stale']->name])
            ->assertSee(__('Minimum match score: :score%', ['score' => 90]))
            ->assertSee(__('Pending reviews'))
            ->assertSee('data-url="' . route('pods.match-reviews.decision', [$this->pod, $f['firstReview']]) . '"', false)
            ->assertSee('data-mr-action="approve"', false)->assertSee('data-mr-action="reject"', false)->assertSee('data-mr-action="exclude"', false)
            ->assertSee('aria-controls="mr-why-' . $f['firstReview']->id . '"', false)
            ->assertSee(__('All required skills matched'))
            ->assertSee('Has all 1 required skills.')
            ->assertSee(__('Current minimum: :score%', ['score' => 90]))
            ->assertSee('version ' . config('pod_matching.version'))
            ->assertSee(__('This match no longer meets the current :score% minimum. Approval is disabled.', ['score' => 90]))
            ->assertSee(route('employee.show', $f['first']->id), false)
            ->assertSee(__('Approve :name', ['name' => $f['stale']->name]));

        $html = $page->getContent();
        $this->assertMatchesRegularExpression('/data-mr-action="approve" disabled\s+aria-describedby="mr-status-' . $f['staleReview']->id . '"/', $html, 'stale proposal: approval disabled');
        foreach (['+94 77 123 4567', '12 Secret Lane', '00999', $f['first']->email, '"matched":', 'skills_all_matched'] as $private) {
            $this->assertStringNotContainsString($private, $html);
        }
        $this->assertMatchesRegularExpression('/data-mr-empty\s+hidden/', $html);

        // After every decision the empty state is shown.
        foreach (['firstReview', 'secondReview', 'staleReview'] as $key) {
            $this->actingAs($this->company)->postJson(route('pods.match-reviews.decision', [$this->pod, $f[$key]]), ['action' => 'reject'])->assertOk();
        }
        $empty = $this->actingAs($this->company)->get(route('pods.match-reviews.index', $this->pod))->assertOk()->assertSee(__('No matches waiting for review'));
        $this->assertDoesNotMatchRegularExpression('/data-mr-empty\s+hidden/', $empty->getContent());
    }

    public function test_pod_management_links_to_the_queue_only_for_reviewers(): void
    {
        $this->configurePod('pending_approval');
        $render = fn (User $user) => view('pod.manage.nav', ['podId' => $this->pod->id, 'pod' => $this->pod])->render();

        $this->actingAs($this->company);
        $this->assertStringContainsString(route('pods.match-reviews.index', $this->pod), $render($this->company));
        $member = $this->qualifiedEmployee();
        app(PodStateTransitionService::class)->enter($this->pod, $member, State::OUTER_CIRCLE, $this->company);
        $this->actingAs($member->fresh());
        $this->assertStringNotContainsString(route('pods.match-reviews.index', $this->pod), $render($member));
    }

    public function test_every_new_string_is_in_the_english_catalog(): void
    {
        $catalog = json_decode(file_get_contents(resource_path('lang/en.json')), true);
        $missing = [];
        foreach ([
            resource_path('views/pod/match_reviews/index.blade.php'), resource_path('views/pod/match_reviews/card.blade.php'),
            app_path('Http/Controllers/PodMatchReviewController.php'), app_path('Services/Matching/PodMatchReviewService.php'),
            app_path('Services/Matching/MatchReviewConflict.php'), app_path('Services/Matching/MatchReviewNotApprovable.php'),
            app_path('Enums/Matching/MatchReviewDecision.php'),
        ] as $file) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                if (!array_key_exists(str_replace("\\'", "'", $key), $catalog)) {
                    $missing[] = $key;
                }
            }
        }
        $this->assertSame([], array_values(array_unique($missing)));
        $this->assertArrayHasKey('Match reviews', $catalog);
    }
}
