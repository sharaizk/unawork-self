<?php

namespace App\Http\Requests\Pods;

use App\Services\Company\BusinessOnboardingCompletionService;
use Illuminate\Foundation\Http\FormRequest;

/** Start a guided POD draft (UNA-011). Only a title is required; everything else comes later. */
class StorePodDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(BusinessOnboardingCompletionService::class)->companyWorkspace($this->user()) !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:' . (int) config('pod_onboarding.max_title_length', 255)],
            'description' => ['nullable', 'string', 'max:' . (int) config('pod_onboarding.max_description_length', 5000)],
            // Per-form token so a resubmitted/refreshed request resumes the same draft.
            'draft_token' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => __('POD title'), 'description' => __('POD description')];
    }
}
