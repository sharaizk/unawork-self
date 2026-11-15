<?php

namespace App\Services\Pod;

use App\Models\Pod;
use Illuminate\Http\Request;

/**
 * Publication (or a published-POD edit) was refused because a configuration or the company profile
 * is incomplete (UNA-011). Nothing was changed. Renders 422 JSON with both completeness summaries,
 * or redirects to the POD setup page with the message.
 */
abstract class PodPublicationRefused extends \DomainException
{
    /**
     * @param array{percentage:int, required:int, missing:string[]} $podStatus
     * @param array{percentage:int, required:int, missing:string[]} $companyStatus
     */
    public function __construct(
        string $message,
        public readonly Pod $pod,
        public readonly array $podStatus,
        public readonly array $companyStatus,
    ) {
        parent::__construct($message);
    }

    public function payload(): array
    {
        return [
            'message' => $this->getMessage(),
            'pod_completion_percentage' => $this->podStatus['percentage'],
            'required_pod_percentage' => $this->podStatus['required'],
            'missing_pod_requirements' => $this->podStatus['missing'],
            'company_completion_percentage' => $this->companyStatus['percentage'],
            'required_company_percentage' => $this->companyStatus['required'],
            'missing_company_requirements' => $this->companyStatus['missing'],
            'setup_url' => route('pods.setup.edit', $this->pod),
            'onboarding_url' => route('company.onboarding.edit'),
        ];
    }

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($this->payload(), 422);
        }

        return redirect()->route('pods.setup.edit', ['pod' => $this->pod, 'step' => 'review'])->with('error', $this->getMessage());
    }
}
