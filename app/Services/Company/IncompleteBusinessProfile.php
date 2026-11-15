<?php

namespace App\Services\Company;

use Illuminate\Http\Request;

/**
 * A company below the required business-onboarding completion tried to publish (create) a POD
 * (UNA-010). Thrown before anything is uploaded or written. Renders as a redirect to the company
 * profile with a warning, or as a 409 JSON response for API/AJAX requests.
 */
class IncompleteBusinessProfile extends \DomainException
{
    /** @param string[] $missing */
    public function __construct(
        public readonly int $percentage,
        public readonly int $required,
        public readonly array $missing,
    ) {
        parent::__construct(__('Complete your company profile before publishing a POD. Your profile is :percentage% complete; :required% is required.', [
            'percentage' => $percentage,
            'required' => $required,
        ]));
    }

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'completion_percentage' => $this->percentage,
                'required_percentage' => $this->required,
                'missing_requirements' => $this->missing,
                'onboarding_url' => route('company.onboarding.edit'),
            ], 409);
        }

        return redirect()->route('company.onboarding.edit')->with('error', $this->getMessage());
    }
}
