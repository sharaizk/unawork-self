<?php

namespace App\Http\Requests\PodDocument;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for reordering POD documents and onboarding materials (UNA-021).
 */
class ReorderPodDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pod = $this->route('pod');
        return $pod && $this->user() && $this->user()->can('manageDocuments', $pod);
    }

    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ordered_ids' => __('Material order'),
            'ordered_ids.*' => __('Material ID'),
        ];
    }
}
