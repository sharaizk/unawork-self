<?php

namespace App\Providers;

use PayHere\PayHereServiceProvider as PackagePayHereServiceProvider;
use ReflectionClass;
use Spatie\LaravelPackageTools\Package;

/**
 * Loads laravel-payhere/laravel-payhere without its routes and migrations.
 *
 * The package is listed under "dont-discover" in composer.json because its own
 * service provider registers:
 *  - unauthenticated /payhere/api/* routes (charge, refund, capture, cancel subscription),
 *  - a /payhere/webhook route bound to a single, global set of credentials,
 *  - auto-run migrations (payhere_payments, subscriptions) that deploys would apply.
 *
 * UnaWork needs per-workspace credentials, so it uses only the package's config,
 * checkout form builder and checkout view, and handles notifications itself
 * (see App\Services\PayHereService and PayHerePaymentController).
 */
class PayHereServiceProvider extends PackagePayHereServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('payhere')
            ->hasConfigFile()
            ->hasViews();
    }

    /**
     * Resolve paths (config, views) relative to the vendor package, not app/.
     */
    protected function getPackageBaseDir(): string
    {
        return dirname((new ReflectionClass(PackagePayHereServiceProvider::class))->getFileName());
    }
}
