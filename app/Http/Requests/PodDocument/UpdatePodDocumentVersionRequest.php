<?php

namespace App\Http\Requests\PodDocument;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for updating an existing draft version of a POD document or onboarding material (UNA-020, UNA-021).
 */
class UpdatePodDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pod = $this->route('pod');
        return $pod && $this->user() && $this->user()->can('manageDocuments', $pod);
    }

    public function rules(): array
    {
        $maxKb = (int) config('pod_materials.max_file_size_kb', 20480);
        $allowedExtensions = implode(',', config('pod_materials.allowed_extensions', ['pdf', 'doc', 'docx']));
        $allowedMimes = implode(',', config('pod_materials.allowed_mimes', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]));

        return [
            'content' => ['nullable', 'string'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'file' => ['nullable', 'file', "mimes:{$allowedExtensions}", "mimetypes:{$allowedMimes}", "max:{$maxKb}"],
            'change_summary' => ['nullable', 'string', 'max:255'],
            'publish' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        $maxKb = (int) config('pod_materials.max_file_size_kb', 20480);
        $limitMb = round($maxKb / 1024, 1);
        $allowed = strtoupper(implode(', ', config('pod_materials.allowed_extensions', ['pdf', 'doc', 'docx'])));

        return [
            'file.mimes' => __('The file must be a file of type: :types.', ['types' => $allowed]),
            'file.mimetypes' => __('The file must be a file of type: :types.', ['types' => $allowed]),
            'file.max' => __('The file size may not be greater than :limit MB.', ['limit' => $limitMb]),
            'link_url.url' => __('The link must be a valid URL starting with http:// or https://.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'content' => __('Version content'),
            'link_url' => __('External link URL'),
            'file' => __('Uploaded file'),
            'change_summary' => __('Change summary'),
            'publish' => __('Publish'),
        ];
    }
}
