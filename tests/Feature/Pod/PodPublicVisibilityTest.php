<?php

namespace Tests\Feature\Pod;

use App\Models\Pod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-011: drafts never reach public surfaces; published (incl. pre-existing) PODs stay visible.
 * The landing views are replaced by stubs that print the POD titles they receive, so these tests
 * cover the controllers' queries rather than the landing layout.
 */
class PodPublicVisibilityTest extends PodTestCase
{
    private string $stubViews;
    private Pod $draft;
    private Pod $published;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('projects', fn (Blueprint $t) => $t->boolean('private')->default(false));
        Schema::create('news', function (Blueprint $t) {
            $t->id();
            $t->string('newsable_type')->nullable();
            $t->unsignedBigInteger('newsable_id')->nullable();
            $t->string('title')->nullable();
            $t->timestamps();
        });
        foreach (['job_vacancy_skill' => 'job_vacancy_id'] as $table => $key) {
            Schema::create($table, function (Blueprint $t) use ($key) {
                $t->id();
                $t->unsignedBigInteger($key);
                $t->unsignedBigInteger('skill_id');
                $t->timestamps();
            });
        }

        $this->stubViews = sys_get_temp_dir() . '/una011-views-' . uniqid();
        $titles = "@foreach (\$pods as \$p)[{{ \$p->title }}]@endforeach";
        foreach ([
            'pod/list' => $titles, 'pod/landing/pod_list' => $titles, 'find_work/index' => $titles,
            'company/landing/index' => $titles, 'pod/landing/index' => 'pod:{{ $pod->title }}', 'news/list' => 'news:{{ $pod->title }}',
        ] as $view => $content) {
            File::ensureDirectoryExists(dirname("{$this->stubViews}/{$view}.blade.php"));
            File::put("{$this->stubViews}/{$view}.blade.php", $content);
        }
        $this->app['view']->getFinder()->prependLocation($this->stubViews);

        $this->draft = $this->pods()->createDraft($this->company, ['title' => 'Secret Draft']);
        $this->published = $this->pods()->createDraft($this->company, ['title' => 'Live POD']);
        Pod::whereKey($this->published->id)->update(['status' => 'published', 'published_at' => now()]); // e.g. a migrated POD
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->stubViews);
        parent::tearDown();
    }

    public function test_drafts_are_absent_from_listings_search_find_work_company_pages_and_the_api(): void
    {
        $this->get(route('pods.list'))->assertOk()->assertSee('[Live POD]', false)->assertDontSee('Secret Draft');
        $this->assertStringContainsString('[Live POD]', $this->getJson(route('pods.search'))->assertOk()->json('pods'));
        $this->assertStringNotContainsString('Secret Draft', $this->getJson(route('pods.search', ['name' => 'Secret']))->json('pods'));
        $this->get(route('landing.find-work'))->assertOk()->assertSee('[Live POD]', false)->assertDontSee('Secret Draft');
        $this->get(route('landing.company.show', $this->company->ownWorkspace()->id))->assertOk()->assertSee('[Live POD]', false)->assertDontSee('Secret Draft');

        $viewer = $this->account('employee');
        $this->assertSame(['Live POD'], collect($this->actingAs($viewer)->getJson('/api/pod')->assertOk()->json('data'))->pluck('title')->all());
        $this->actingAs($viewer)->getJson('/api/pod/' . $this->draft->id)->assertNotFound();
        $this->actingAs($this->company)->getJson('/api/pod/' . $this->draft->id)->assertOk(); // the owner, through the authenticated API
    }

    public function test_a_draft_public_url_is_404_for_everyone_and_published_pods_stay_visible(): void
    {
        auth()->logout();
        $this->get(route('landing.pods.show', $this->draft))->assertNotFound();
        $this->get(route('landing.pods.news.list', $this->draft))->assertNotFound();
        $this->actingAs($this->account('employee'))->get(route('landing.pods.show', $this->draft))->assertNotFound();
        $this->actingAs($this->company)->get(route('landing.pods.show', $this->draft))->assertNotFound();

        $this->get(route('landing.pods.show', $this->published))->assertOk()->assertSee('pod:Live POD');
        $this->get(route('landing.pods.news.list', $this->published))->assertOk();

        // Applications are accepted only for published PODs.
        $employee = $this->account('employee');
        $this->assertFalse($employee->can('requestToJoin', $this->draft));
        $this->assertTrue($employee->can('requestToJoin', $this->published->fresh()));
    }
}
