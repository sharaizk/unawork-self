<?php

namespace Tests\Feature\Matching;

use App\Enums\Pod\PodStatus;
use App\Enums\PodUser\State;
use App\Models\Client;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodMatchReason;
use App\Models\PodRecommendationDismissal;
use App\Models\PodUser;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use App\Services\Matching\PodMatchService;
use App\Services\Matching\PodRecommendationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * UNA-017: the employee recommendation screen: selection/ranking, explanations, apply, dismiss,
 * access and manual discovery. In-memory SQLite only.
 *
 * qualifiedEmployee() scores 100.00 against Engineer + PHP, 68.75 against Engineer + PHP + Docker,
 * and 80.00 (disqualified) against Engineer + PHP + mandatory project type "hourly".
 */
class PodRecommendationsTest extends WorkflowTestCase
{
    private int $podCounter = 0;

    private function recommendations(): PodRecommendationService
    {
        return app(PodRecommendationService::class);
    }

    private function publishedPod(int $threshold = 0, ?string $title = null, ?string $status = 'published'): Pod
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => $title ?? 'POD ' . (++$this->podCounter)]);
        $pod->forceFill([
            'matching_mode' => 'recommend_only', 'minimum_match_threshold' => $threshold,
            'status' => $status === 'published' ? PodStatus::PUBLISHED : PodStatus::DRAFT,
            'published_at' => $status === 'published' ? now() : null,
        ])->save();

        return $pod->fresh();
    }

    private function req(Pod $pod, array $attributes = [], ?array $skills = null): PodWorkerRequirement
    {
        $requirement = new PodWorkerRequirement($attributes + ['is_active' => true, 'headcount' => 1, 'job_title_id' => $this->engineer->id]);
        $requirement->forceFill(['pod_id' => $pod->id])->save();
        $requirement->skills()->sync($skills ?? [$this->php->id]);

        return $requirement->fresh();
    }

    private function evaluate(PodWorkerRequirement $requirement, User $user, string $at = '2026-06-15 12:00:00'): PodMatch
    {
        return app(PodMatchService::class)->evaluate($requirement, $user->fresh(), CarbonImmutable::parse($at, 'UTC'));
    }

    private function rankedPodIds(User $user): array
    {
        return $this->recommendations()->rankedMatches($user->fresh())->pluck('pod_id')->map(fn ($id) => (int) $id)->all();
    }

    // ------------------------------------------------------------------ selection and ranking

    public function test_only_qualified_current_matches_are_recommended_one_per_pod_ranked_by_score(): void
    {
        $user = $this->qualifiedEmployee();
        $other = $this->qualifiedEmployee();

        $top = $this->publishedPod();
        $this->evaluate($this->req($top), $user);                                                  // 100
        $second = $this->publishedPod();
        $this->evaluate($this->req($second, [], [$this->php->id, $this->sql->id, $this->laravel->id, $this->docker->id]), $user); // 84.38
        $mixed = $this->publishedPod(60);
        $eligible = $this->req($mixed, [], [$this->php->id, $this->docker->id]);                     // 68.75 eligible
        $this->evaluate($eligible, $user);
        $this->evaluate($this->req($mixed, ['project_type' => 'hourly', 'mandatory_criteria' => ['project_type']]), $user); // 80 disqualified

        $below = $this->publishedPod(70);
        $this->evaluate($this->req($below, [], [$this->php->id, $this->docker->id]), $user);         // 68.75 < 70
        $disqualified = $this->publishedPod();
        $this->evaluate($this->req($disqualified, ['project_type' => 'hourly', 'mandatory_criteria' => ['project_type']]), $user);
        $notScorable = $this->publishedPod();
        $this->evaluate($this->req($notScorable, ['job_title_id' => null, 'preferred_work_mode' => 'remote'], []), $user);
        $draft = $this->publishedPod(0, null, 'draft');
        $this->evaluate($this->req($draft), $user);
        $inactivePod = $this->publishedPod();
        $inactive = $this->req($inactivePod);
        $this->evaluate($inactive, $user);
        $inactive->forceFill(['is_active' => false])->save();
        $deleted = $this->publishedPod();
        $this->evaluate($this->req($deleted), $user);
        DB::table('pods')->where('id', $deleted->id)->delete();
        $othersOnly = $this->publishedPod();
        $this->evaluate($this->req($othersOnly), $other);

        $ranked = $this->recommendations()->rankedMatches($user->fresh());
        $this->assertSame([$top->id, $second->id, $mixed->id], $ranked->pluck('pod_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame(['100.00', '84.38', '68.75'], $ranked->pluck('score')->all());
        $this->assertSame($eligible->id, (int) $ranked[2]->pod_worker_requirement_id, 'the eligible requirement beats a higher disqualified one');
        $this->assertSame([$othersOnly->id], $this->rankedPodIds($other), 'only the authenticated employee’s own matches');
    }

    public function test_ties_break_on_generation_time_then_pod_id_and_ranking_is_stable(): void
    {
        $user = $this->qualifiedEmployee();
        [$a, $b, $c] = [$this->publishedPod(), $this->publishedPod(), $this->publishedPod()];
        $this->evaluate($this->req($a), $user, '2026-06-15 12:00:00');
        $this->evaluate($this->req($b), $user, '2026-06-16 12:00:00');
        $this->evaluate($this->req($c), $user, '2026-06-15 12:00:00');

        $this->assertSame([$b->id, $a->id, $c->id], $this->rankedPodIds($user), 'newer first, then lowest POD id');
        $this->assertSame($this->rankedPodIds($user), $this->rankedPodIds($user));
    }

    public function test_results_of_another_algorithm_version_are_hidden_until_regenerated(): void
    {
        $user = $this->qualifiedEmployee();
        $pod = $this->publishedPod();
        $this->evaluate($this->req($pod), $user);
        $this->assertSame([$pod->id], $this->rankedPodIds($user));

        config(['pod_matching.version' => 'una-099.1']);
        $this->assertSame([], $this->rankedPodIds($user), 'stale version');
        $this->assertSame('preparing', $this->recommendations()->emptyReason($user->fresh()));

        config(['pod_matching.version' => 'una-013.1']);
        PodMatch::query()->update(['algorithm' => 'retired']);
        $this->assertSame([], $this->rankedPodIds($user), 'other algorithm');
    }

    public function test_pagination_is_per_pod_and_reading_never_recalculates_or_queries_per_card(): void
    {
        config(['pod_matching.recommendations.per_page' => 2]);
        $user = $this->qualifiedEmployee();
        $pods = [];
        foreach (range(1, 5) as $i) {
            $pods[] = $pod = $this->publishedPod();
            $this->evaluate($this->req($pod), $user, "2026-06-1{$i} 12:00:00");
            $this->evaluate($this->req($pod, [], [$this->php->id, $this->docker->id]), $user); // a second requirement per POD
        }
        $before = $this->snapshot(['pod_matches', 'pod_match_reasons', 'pod_users']);

        $page1 = $this->recommendations()->paginate($user->fresh(), 1);
        $page3 = $this->recommendations()->paginate($user->fresh(), 3);
        $this->assertSame([5, 3, 2, 1], [$page1->total(), $page1->lastPage(), $page1->count(), $page3->count()]);
        $this->assertSame([$pods[4]->id, $pods[3]->id], collect($page1->items())->map(fn ($r) => $r->pod->id)->all());
        $this->assertSame([$pods[0]->id], collect($page3->items())->map(fn ($r) => $r->pod->id)->all());

        // Constant queries whatever the page size; nothing written or queued.
        $count = function (int $perPage) use ($user) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->recommendations()->paginate($user->fresh(), 1, $perPage);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $this->assertSame($count(1), $count(5), 'no N+1 across cards');
        $this->assertEquals($before, $this->snapshot(['pod_matches', 'pod_match_reasons', 'pod_users']));
        Bus::assertNothingDispatched();
    }

    // ------------------------------------------------------------------ page and explanations

    public function test_the_page_shows_ranked_cards_with_explanations_and_manual_discovery(): void
    {
        $user = $this->qualifiedEmployee();
        $pod = $this->publishedPod(0, 'A remarkably long POD title for platform engineering and developer experience teams');
        $this->evaluate($this->req($pod, ['project_type' => 'long_term', 'mandatory_criteria' => ['project_type'], 'preferred_work_mode' => 'remote', 'location' => 'Colombo']), $user);
        $low = $this->publishedPod(0, 'Second choice POD');
        $this->evaluate($this->req($low, [], [$this->php->id, $this->docker->id]), $user);

        $page = $this->actingAs($user)->get(route('pod-recommendations.index'))->assertOk();

        $page->assertSeeInOrder(['PODs recommended for you', 'A remarkably long POD title', 'Second choice POD'])
            ->assertSee('href="' . route('pods.list') . '"', false)
            ->assertSee(__('Browse all PODs'))
            ->assertSee('data-url="' . route('api.pods.join', ['id' => $pod->id]) . '"', false)
            ->assertSee('data-url="' . route('pod-recommendations.dismiss', $pod->id) . '"', false)
            ->assertSee('aria-controls="pr-why-' . $pod->id . '"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee(__('Profile match :score%', ['score' => '100']))
            ->assertSee(__('All required skills matched'))
            ->assertSee(__('Matches your primary role'))
            ->assertSee('Has all 1 required skills.')                    // PodMatchPresenter explanation
            ->assertSee(__('Required'))                                     // mandatory badge
            ->assertSee(__(':awarded of :possible pts', ['awarded' => '25', 'possible' => 25]))
            ->assertSee('version ' . config('pod_matching.version'))
            ->assertSee(__('Structured profile matching'))
            ->assertSee('Colombo')
            ->assertSee(__('Remote'))
            ->assertSee(route('pod-recommendations.index'), false)          // sidebar entry
            ->assertSee(__('PODs for you'))
            ->assertSee(__('POD Recommendations'));

        $html = $page->getContent();
        $this->assertStringNotContainsString('"matched":', $html, 'no raw reason context');
        $this->assertStringNotContainsString('skills_all_matched', $html, 'no internal codes');
        $this->assertStringNotContainsString('App\\', $html);
        // Only the feature's own markup (the layout's inline scripts carry random tokens), as a word.
        $feature = substr($html, strpos($html, 'data-pr-root'), strpos($html, 'pod-recommendations.js') - strpos($html, 'data-pr-root'));
        $this->assertDoesNotMatchRegularExpression('/\bAI\b/', strip_tags('<div ' . $feature), 'deterministic results are not labelled AI');
    }

    public function test_existing_memberships_show_status_or_leave_the_list(): void
    {
        $user = $this->qualifiedEmployee();
        $pods = [];
        foreach ([null, State::PENDING_BUSINESS_APPROVAL, State::MATCHED, State::OUTER_CIRCLE, State::INNER_CIRCLE, State::SUSPENDED, State::REJECTED, State::REMOVED] as $state) {
            $pods[$state?->value ?? 'none'] = $pod = $this->publishedPod();
            $this->evaluate($this->req($pod), $user);
            if ($state) {
                PodUser::query()->forceCreate(['pod_id' => $pod->id, 'user_id' => $user->id, 'permission' => 'Member', 'state' => $state->value]); // fixture
            }
        }

        $this->assertEqualsCanonicalizing([$pods['none']->id, $pods['pending_business_approval']->id, $pods['matched']->id], $this->rankedPodIds($user));

        $page = $this->actingAs($user)->get(route('pod-recommendations.index'))->assertOk();
        $page->assertSee('data-url="' . route('api.pods.join', ['id' => $pods['none']->id]) . '"', false)
            ->assertDontSee('data-url="' . route('api.pods.join', ['id' => $pods['pending_business_approval']->id]) . '"', false)
            ->assertDontSee('data-url="' . route('api.pods.join', ['id' => $pods['matched']->id]) . '"', false)
            ->assertSee(__('Application sent'))
            ->assertSee(__('Invitation pending'));
    }

    public function test_empty_states_offer_manual_discovery_and_profile_update(): void
    {
        $user = $this->qualifiedEmployee();
        $open = $this->publishedPod();
        $requirement = $this->req($open);

        $this->actingAs($user)->get(route('pod-recommendations.index'))->assertOk()
            ->assertSee('We’re preparing your POD recommendations.')
            ->assertSee('href="' . route('pods.list') . '"', false)
            ->assertSee('href="' . route('my-profile.edit') . '"', false);
        Bus::assertNothingDispatched(); // GET never triggers matching

        $open->forceFill(['minimum_match_threshold' => 100])->save();
        $requirement->skills()->sync([$this->php->id, $this->docker->id]);
        $this->evaluate($requirement->fresh(), $user);
        $this->actingAs($user)->get(route('pod-recommendations.index'))->assertOk()
            ->assertSee(__('No strong POD matches yet.'))
            ->assertSee(__('Update profile'));
    }

    // ------------------------------------------------------------------ access

    public function test_only_complete_employee_accounts_can_open_the_screen(): void
    {
        $this->get(route('pod-recommendations.index'))->assertRedirect(route('login'));
        $this->actingAs($this->incompleteEmployee())->get(route('pod-recommendations.index'))->assertRedirect(route('my-profile.edit'));
        $this->actingAs($this->company)->get(route('pod-recommendations.index'))->assertForbidden();
        $this->actingAs($this->account('administrator'))->get(route('pod-recommendations.index'))->assertForbidden();

        // A client never gets in (in a browser the web guard has no user; here the client guard is
        // the default, so `verified` stops it first). Either way: a redirect, no recommendation data.
        $client = Client::forceCreate(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'x']);
        $this->actingAs($client, 'client')->get(route('pod-recommendations.index'))->assertRedirect()->assertDontSee('PODs recommended for you');
    }

    // ------------------------------------------------------------------ apply

    public function test_applying_uses_the_canonical_join_workflow_and_is_idempotent(): void
    {
        $user = $this->qualifiedEmployee();
        $pod = $this->publishedPod();
        $this->evaluate($this->req($pod), $user);
        Sanctum::actingAs($user);

        $this->postJson(route('api.pods.join', ['id' => $pod->id]))->assertOk()->assertJsonPath('state', 'pending_business_approval');
        $membership = PodUser::where('pod_id', $pod->id)->where('user_id', $user->id)->sole();
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, $membership->state);
        $this->assertSame([1, $user->id, 'Applied to join the POD'], [
            DB::table('pod_user_state_histories')->where('pod_user_id', $membership->id)->count(),
            (int) DB::table('pod_user_state_histories')->where('pod_user_id', $membership->id)->value('actor_id'),
            DB::table('pod_user_state_histories')->where('pod_user_id', $membership->id)->value('reason'),
        ]);
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_type', \App\Models\UserWorkspace::class)
            ->whereIn('model_id', \App\Models\UserWorkspace::where('user_id', $user->id)->where('workspace_id', $pod->workspace->id)->pluck('id'))->count(), 'no circle access while pending');

        $this->postJson(route('api.pods.join', ['id' => $pod->id]))->assertStatus(409)->assertJsonPath('status', 'already_requested');
        $this->assertSame(1, PodUser::where('pod_id', $pod->id)->where('user_id', $user->id)->count());
        $this->assertSame(0, PodUser::where('state', State::INNER_CIRCLE->value)->where('user_id', $user->id)->count());

        // The card now shows the status instead of Apply.
        $this->actingAs($user)->get(route('pod-recommendations.index'))->assertSee(__('Application sent'))
            ->assertDontSee('data-url="' . route('api.pods.join', ['id' => $pod->id]) . '"', false);
    }

    public function test_only_complete_employees_can_apply_and_only_to_published_pods(): void
    {
        $pod = $this->publishedPod();
        $this->req($pod);
        $draft = $this->publishedPod(0, null, 'draft');

        foreach (['company' => $this->account('company'), 'administrator' => $this->account('administrator'), 'incomplete' => $this->incompleteEmployee()] as $type => $account) {
            Sanctum::actingAs($account);
            $this->postJson(route('api.pods.join', ['id' => $pod->id]))->assertForbidden();
            $this->assertSame(0, PodUser::where('user_id', $account->id)->count(), $type);
        }

        // Another employee's recommendation is no shortcut: the policy still decides.
        $employee = $this->qualifiedEmployee();
        Sanctum::actingAs($employee);
        $this->postJson(route('api.pods.join', ['id' => $draft->id]))->assertForbidden();
        $this->assertSame(0, PodUser::where('user_id', $employee->id)->count());
    }

    // ------------------------------------------------------------------ dismiss

    public function test_dismissing_hides_a_pod_for_that_employee_only_and_changes_nothing_else(): void
    {
        $user = $this->qualifiedEmployee();
        $other = $this->qualifiedEmployee();
        $pod = $this->publishedPod(0, 'Dismissable POD');
        $requirement = $this->req($pod);
        $this->evaluate($requirement, $user);
        $this->evaluate($requirement, $other);
        $before = $this->snapshot(['pod_matches', 'pod_match_reasons', 'pod_users', 'pod_user_state_histories', 'user_workspaces']);

        $this->actingAs($user)->postJson(route('pod-recommendations.dismiss', $pod), ['user_id' => $other->id])
            ->assertOk()->assertJsonPath('status', 'success');
        $this->actingAs($user)->postJson(route('pod-recommendations.dismiss', $pod))->assertOk();

        $dismissal = PodRecommendationDismissal::sole();
        $this->assertSame([$user->id, $pod->id], [(int) $dismissal->user_id, (int) $dismissal->pod_id], 'never on behalf of user_id input');
        $this->assertNotNull($dismissal->dismissed_at);
        $this->assertSame([], $this->rankedPodIds($user));
        $this->assertSame([$pod->id], $this->rankedPodIds($other), 'other employees unaffected');
        $this->assertEquals($before, $this->snapshot(['pod_matches', 'pod_match_reasons', 'pod_users', 'pod_user_state_histories', 'user_workspaces']));

        $this->actingAs($user)->get(route('pod-recommendations.index'))->assertDontSee('Dismissable POD');
        // Manual discovery still lists it (landing view stubbed to the titles it receives, as in PodPublicVisibilityTest).
        $stubs = sys_get_temp_dir() . '/una017-views-' . uniqid();
        \Illuminate\Support\Facades\File::ensureDirectoryExists("{$stubs}/pod");
        \Illuminate\Support\Facades\File::put("{$stubs}/pod/list.blade.php", '@foreach ($pods as $p)[{{ $p->title }}]@endforeach');
        $this->app['view']->getFinder()->prependLocation($stubs);
        try {
            $this->actingAs($user)->get(route('pods.list'))->assertOk()->assertSee('[Dismissable POD]', false);
        } finally {
            \Illuminate\Support\Facades\File::deleteDirectory($stubs);
        }

        // Regenerating keeps it dismissed.
        $this->evaluate($requirement, $user, '2026-06-20 12:00:00');
        $this->assertSame([], $this->rankedPodIds($user));

        // Non-JSON fallback and non-employees.
        $second = $this->publishedPod();
        $this->actingAs($user)->post(route('pod-recommendations.dismiss', $second))
            ->assertRedirect(route('pod-recommendations.index'))->assertSessionHas('success');
        $this->actingAs($this->company)->postJson(route('pod-recommendations.dismiss', $pod))->assertForbidden();
        $this->assertSame(2, PodRecommendationDismissal::count());
    }

    public function test_dismissals_cascade_with_users_and_pods_and_the_migration_reverses(): void
    {
        $user = $this->qualifiedEmployee();
        [$a, $b] = [$this->publishedPod(), $this->publishedPod()];
        foreach ([$a, $b] as $pod) {
            $this->actingAs($user)->postJson(route('pod-recommendations.dismiss', $pod))->assertOk();
        }
        $another = $this->qualifiedEmployee();
        $this->actingAs($another)->postJson(route('pod-recommendations.dismiss', $a))->assertOk();
        $this->assertSame(3, PodRecommendationDismissal::count());

        DB::table('pods')->where('id', $b->id)->delete();
        $this->assertSame(2, PodRecommendationDismissal::count(), 'POD deleted');
        DB::table('users')->where('id', $another->id)->delete();
        $this->assertSame(1, PodRecommendationDismissal::count(), 'user deleted');
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('pod_recommendation_dismissals', 'prd_user_pod_unique'));

        (require database_path('migrations/2026_11_19_000400_create_pod_recommendation_dismissals_table.php'))->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('pod_recommendation_dismissals'));
    }

    // ------------------------------------------------------------------ translations

    public function test_every_new_string_is_in_the_english_catalog(): void
    {
        $catalog = json_decode(file_get_contents(resource_path('lang/en.json')), true);
        $files = [
            resource_path('views/pod/recommendations/index.blade.php'),
            resource_path('views/pod/recommendations/card.blade.php'),
            app_path('Http/Controllers/PodRecommendationController.php'),
            app_path('Services/Matching/PodRecommendationDismissalService.php'),
            app_path('Services/Matching/PodMatchPresenter.php'),
        ];
        $missing = [];
        foreach ($files as $file) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                $key = str_replace("\\'", "'", $key);
                if (!array_key_exists($key, $catalog)) {
                    $missing[] = $key;
                }
            }
        }
        $this->assertSame([], array_values(array_unique($missing)));
    }
}
