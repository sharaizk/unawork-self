<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Chatify\Http\Controllers\MessagesController;
use App\Http\Controllers\MessagesController as AppMessagesController;
use App\Models\Workspace;
use App\Observers\WorkspaceObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // UNA-016: the configured POD matching algorithm (deterministic by default).
        $this->app->bind(\App\Contracts\PodMatchingAlgorithm::class, fn ($app) => $app->make(\App\Services\Matching\PodMatchingAlgorithmResolver::class)->resolve());

        // UNA-022: the configured AI document text extraction provider (Gemini by default).
        $this->app->bind(\App\Contracts\DocumentTextExtractionProvider::class, fn ($app) => $app->make(\App\Services\PodDocument\Extraction\DocumentTextExtractionResolver::class)->resolve());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
       Schema::defaultStringLength(191);
       $this->app->bind(MessagesController::class, AppMessagesController::class);
       Workspace::observe(WorkspaceObserver::class);
    }
}
