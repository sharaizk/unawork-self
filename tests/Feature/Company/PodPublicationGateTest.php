<?php

namespace Tests\Feature\Company;

use App\Models\Pod;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\Company\IncompleteBusinessProfile;
use App\Services\PodService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-010: creating (= publishing) a POD requires a complete company profile, enforced in
 * PodService::createPod() before anything is uploaded or written. In-memory SQLite only.
 */
class PodPublicationGateTest extends TestCase
{
    use UsesInMemorySqlite;

    private User $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->createAdminLayoutSchema();
        Schema::create('pods_skills', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pod_id');
            $t->unsignedBigInteger('skill_id');
            $t->timestamps();
        });
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Storage::fake('public');
        Mail::fake();
        Bus::fake();

        $this->company = $this->account('company');
    }

    public function test_an_incomplete_company_cannot_publish_through_the_api_and_nothing_is_left_behind(): void
    {
        $this->completeBusinessProfile($this->company);
        $this->company->ownWorkspace()->forceFill(['description' => 'Too short now'])->save();
        $before = $this->snapshot();

        $response = $this->actingAs($this->company)->postJson('/api/pod', [
            'title' => 'New POD', 'description' => 'd', 'image' => UploadedFile::fake()->image('pod.png'),
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('completion_percentage', 90)
            ->assertJsonPath('required_percentage', 100)
            ->assertJsonPath('onboarding_url', route('company.onboarding.edit'))
            ->assertJsonPath('missing_requirements.0', __('Describe your company in at least :count characters', ['count' => 100]))
            ->assertJsonPath('message', __('Complete your company profile before publishing a POD. Your profile is :percentage% complete; :required% is required.', ['percentage' => 90, 'required' => 100]));

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'no uploaded POD image');
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
    }

    public function test_the_service_itself_refuses_an_incomplete_company(): void
    {
        $before = $this->snapshot();

        try {
            app(PodService::class)->createPod(['title' => 'Direct', 'created_by' => $this->company->id, 'image' => UploadedFile::fake()->image('x.png')]);
            $this->fail('POD created');
        } catch (IncompleteBusinessProfile $e) {
            $this->assertSame([0, 100], [$e->percentage, $e->required]);
            $this->assertNotEmpty($e->missing);
        }

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_html_requests_are_redirected_to_the_company_profile(): void
    {
        $exception = new IncompleteBusinessProfile(40, 100, ['Upload your company logo']);

        $response = $exception->render(Request::create('/pods', 'POST'));

        $this->assertSame(route('company.onboarding.edit'), $response->getTargetUrl());
        $this->assertSame($exception->getMessage(), $response->getSession()?->get('error') ?? session('error'));
    }

    public function test_a_complete_company_publishes_normally_until_required_data_is_removed(): void
    {
        $this->completeBusinessProfile($this->company);

        $this->actingAs($this->company)->postJson('/api/pod', ['title' => 'Published'])->assertCreated();
        $pod = Pod::where('title', 'Published')->sole();
        $this->assertNotNull($pod->workspace);
        $this->assertSame(1, DB::table('pod_users')->where('pod_id', $pod->id)->count());

        // Removing mandatory data blocks future POD creation; the existing POD stays as it is.
        $this->company->ownWorkspace()->forceFill(['matching_mode' => null])->save();
        $this->actingAs($this->company)->postJson('/api/pod', ['title' => 'Second'])->assertStatus(409);
        $this->assertSame(['Published'], Pod::pluck('title')->all());
        $this->assertNotNull($pod->fresh()->workspace);
    }

    public function test_accounts_not_subject_to_business_onboarding_are_unaffected(): void
    {
        $employee = $this->account('employee');

        $pod = app(PodService::class)->createPod(['title' => 'Employee POD', 'created_by' => $employee->id]);

        $this->assertTrue($pod->exists);
    }

    public function test_pod_page_shows_completion_and_replaces_the_create_action(): void
    {
        $this->completeBusinessProfile($this->company);
        $this->company->ownWorkspace()->forceFill(['logo' => null])->save();

        $page = $this->actingAs($this->company)->get(route('pods.index'))->assertOk();
        $page->assertSee(__('Company profile: :percentage% complete', ['percentage' => 85]))
            ->assertSee(__('Upload your company logo'))
            ->assertSee(route('company.onboarding.edit'), false)
            ->assertDontSee('data-url="' . route('pods.create') . '"', false)
            ->assertSee('assets/custom/js/pods.js', false);
        $this->assertSame(1, substr_count($page->getContent(), 'axios.min.js'), 'axios only from the layout');
        $this->assertSame(1, substr_count($page->getContent(), 'chosen.jquery.min.js'));

        $this->actingAs($this->company)->get(route('pods.create'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee(__('Complete Company Profile'))->assertDontSee('create-pod-form', false);

        $this->company->ownWorkspace()->forceFill(['logo' => $this->company->ownWorkspace()->id . '_company-logo-x.png'])->save();
        $this->actingAs($this->company)->get(route('pods.index'))->assertOk()
            ->assertSee('data-url="' . route('pods.create') . '"', false)
            ->assertSee(__('Your company profile is complete. You can publish PODs.'));
        $this->actingAs($this->company)->get(route('pods.create'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('create-pod-form', false)->assertSee('data-pod-form-error', false);
    }

    // ================================================================ helpers

    private function snapshot(): array
    {
        return collect(['pods', 'workspaces', 'pod_users', 'pod_user_state_histories', 'user_workspaces', 'model_has_permissions', 'notifications', 'stages', 'bug_stages'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy(DB::raw('1'))->get()->map(fn ($r) => (array) $r)->all()])->all();
    }

    private function account(string $type): User
    {
        $user = User::factory()->{$type}()->create(['plan' => 1, 'email_verified_at' => now()]);
        $workspace = Workspace::create(['name' => "{$user->name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner']);
        $user->forceFill(['currant_workspace' => $workspace->id])->save();

        return $user->fresh();
    }
}
