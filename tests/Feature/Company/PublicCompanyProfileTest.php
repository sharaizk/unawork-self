<?php

namespace Tests\Feature\Company;

use App\Models\Industry;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-010: the public company page reads the structured company profile from the workspace.
 * layouts.landing is replaced by a minimal stub (temporary view path) so the test covers the page
 * itself. In-memory SQLite only.
 */
class PublicCompanyProfileTest extends TestCase
{
    use UsesInMemorySqlite;

    private string $stubViews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        Schema::table('job_vacancies', fn (Blueprint $t) => $t->text('description')->nullable());
        foreach (['job_vacancy_skill' => 'job_vacancy_id'] as $table => $key) {
            Schema::create($table, function (Blueprint $t) use ($key) {
                $t->id();
                $t->unsignedBigInteger($key);
                $t->unsignedBigInteger('skill_id');
                $t->timestamps();
            });
        }
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);

        $this->stubViews = sys_get_temp_dir() . '/una010-views-' . uniqid();
        File::ensureDirectoryExists($this->stubViews . '/layouts');
        File::put($this->stubViews . '/layouts/landing.blade.php', "<main>@yield('content')</main>");
        $this->app['view']->getFinder()->prependLocation($this->stubViews);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->stubViews);
        parent::tearDown();
    }

    public function test_public_page_uses_workspace_fields_logo_and_hides_empty_sections(): void
    {
        $company = $this->company();
        $workspace = $company->ownWorkspace();
        $workspace->forceFill([
            'company' => 'Acme <b>Ltd</b>', 'logo' => $workspace->id . '_company-logo-abc.png',
            'industry_id' => Industry::create(['name' => 'Renewable Energy'])->id,
            'city' => 'Colombo', 'country' => 'Sri Lanka', 'zipcode' => '00100',
            'contact_name' => 'Casey', 'contact_email' => 'casey@acme.example.test', 'telephone' => '+94 11 000',
            'website' => 'https://acme.example.test', 'linkedin' => 'javascript:alert(1)', 'facebook' => 'https://facebook.com/acme',
            'description' => "We build <script>alert(1)</script> solar farms.",
        ])->save();

        $page = $this->get(route('landing.company.show', $workspace->id))->assertOk();

        $page->assertSee('src="' . Utility::get_file('logo/') . $workspace->id . '_company-logo-abc.png"', false)
            ->assertSee('Acme &lt;b&gt;Ltd&lt;/b&gt;', false)
            ->assertSee('data-company-industry>Renewable Energy<', false)
            ->assertSee('Colombo, Sri Lanka, 00100')
            ->assertSee('mailto:casey@acme.example.test', false)
            ->assertSee('href="https://acme.example.test"', false)
            ->assertSee('href="https://facebook.com/acme"', false)
            ->assertDontSee('javascript:alert', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('%', false); // completion is never public
    }

    public function test_logo_falls_back_to_the_owner_avatar_then_the_default(): void
    {
        $company = $this->company();
        $workspace = $company->ownWorkspace();
        $workspace->forceFill(['company' => 'Plain Co', 'description' => null, 'website' => null])->save();

        $page = $this->get(route('landing.company.show', $workspace->id))->assertOk();
        $page->assertSee('logo/logo-light.png', false)->assertDontSee('<h2>' . __('About') . '</h2>', false)->assertDontSee(__('Visit Site'));

        $company->forceFill(['avatar' => 'me.png'])->save();
        $this->get(route('landing.company.show', $workspace->id))->assertOk()->assertSee('users-avatar/me.png', false);
    }

    private function company(): User
    {
        $user = User::factory()->company()->create(['plan' => 1, 'email_verified_at' => now()]);
        $workspace = Workspace::create(['name' => "{$user->name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner']);

        return $user->fresh();
    }
}
