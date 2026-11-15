<?php

namespace App\Http\Middleware;

use App\Contracts\HasAccountType;
use App\Services\Profile\ProfileCompletionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a logged-in employee whose profile is below config('profile.minimum_completion')
 * (UNA-007). Guests, companies, administrators and clients pass through unchanged, so public
 * landing pages keep working for visitors.
 *
 * HTML requests are redirected to /my-profile with a warning; JSON/AJAX requests get a 403 with
 * {message, completion_percentage, required_percentage, profile_url}.
 */
class RequireCompleteEmployeeProfile
{
    public function __construct(private ProfileCompletionService $completion)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if (!$account instanceof HasAccountType || $this->completion->isEligible($account)) {
            return $next($request);
        }

        $percentage = $this->completion->percentage($account);
        $required = $this->completion->required();
        $message = __('Complete at least :required% of your profile to access jobs. Your profile is :percentage% complete.', [
            'required' => $required,
            'percentage' => $percentage,
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'completion_percentage' => $percentage,
                'required_percentage' => $required,
                'profile_url' => route('my-profile.edit'),
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('my-profile.edit')->with('error', $message);
    }
}
