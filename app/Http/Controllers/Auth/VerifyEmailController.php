<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\Onboarding\OnboardingRedirect;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     *
     * @param  \Illuminate\Foundation\Auth\EmailVerificationRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function __invoke(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        // A newly verified employee or company continues with onboarding (UNA-007, UNA-010).
        $onboarding = app(OnboardingRedirect::class)->for($request->user());
        if ($onboarding) {
            return redirect()->route($onboarding['route'], ['verified' => 1]);
        }

        return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
    }
}
