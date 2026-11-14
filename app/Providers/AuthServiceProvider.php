<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Industry;
use App\Models\JobApplication;
use App\Models\JobTitle;
use App\Models\JobVacancy;
use App\Models\News;
use App\Models\Notification;
use App\Models\Pod;
use App\Models\Skill;
use App\Models\UserProject;
use App\Models\Workspace;
use App\Models\ZoomMeeting;
use App\Policies\IndustryPolicy;
use App\Policies\JobApplicationPolicy;
use App\Policies\JobTitlePolicy;
use App\Policies\JobVacancyPolicy;
use App\Policies\NewsPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PodPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SkillPolicy;
use App\Policies\UserProjectPolicy;
use App\Policies\ZoomMeetingPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        Skill::class => SkillPolicy::class,
        Pod::class => PodPolicy::class,
        News::class => NewsPolicy::class,
        Notification::class => NotificationPolicy::class,
        JobVacancy::class => JobVacancyPolicy::class,
        JobApplication::class => JobApplicationPolicy::class,
        Industry::class => IndustryPolicy::class,
        JobTitle::class => JobTitlePolicy::class,
        // Plan-gated project feature checks (viewProjects, ...) are evaluated against the workspace.
        Workspace::class => ProjectPolicy::class,
        // Project join/claim requests (user_projects rows).
        UserProject::class => UserProjectPolicy::class,
        ZoomMeeting::class => ZoomMeetingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
