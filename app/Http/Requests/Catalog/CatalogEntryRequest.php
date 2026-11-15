<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Create/rename a catalog entry (Industry, Skill, JobTitle). Authorizes through the model's
 * policy, normalizes whitespace, and enforces case-insensitive uniqueness within the catalog
 * (ignoring the entry being renamed). Only the label field is accepted.
 *
 * Web validation failures return to the full-page create/edit form (the modal fragment is
 * closed by then); API requests always receive a 422 JSON response.
 */
abstract class CatalogEntryRequest extends FormRequest
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Route name prefix of the web resource (industries, skills, job-titles). */
    abstract protected function routePrefix(): string;

    /** The entry being renamed (null when creating). */
    abstract protected function entry(): ?Model;

    public function authorize(): bool
    {
        $entry = $this->entry();

        return $entry
            ? (bool) $this->user()?->can('update', $entry)
            : (bool) $this->user()?->can('create', $this->modelClass());
    }

    protected function field(): string
    {
        return ($this->modelClass())::catalogColumn();
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input($this->field());
        if (is_string($value)) {
            $this->merge([$this->field() => ($this->modelClass())::normalizeLabel($value)]);
        }
    }

    /** Skills and roles carry industry tags; industries themselves do not. */
    protected function tagsIndustries(): bool
    {
        return in_array('industries', ($this->modelClass())::tagRelations(), true);
    }

    public function rules(): array
    {
        $model = $this->modelClass();
        $entry = $this->entry();
        $industries = $this->tagsIndustries() ? [
            'industry_ids' => ['nullable', 'array', 'max:100'],
            'industry_ids.*' => ['integer', 'distinct', 'exists:industries,id'],
            'industry_ids_present' => ['nullable', 'boolean'],
        ] : [];

        return $industries + [
            $this->field() => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($model, $entry) {
                    $duplicate = $model::query()->whereLabel($value)
                        ->when($entry, fn ($q) => $q->whereKeyNot($entry->getKey()))
                        ->exists();
                    if ($duplicate) {
                        $fail(__('This name is already in the catalog.'));
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return ['industry_ids.*' => __('industry')];
    }

    /** The normalized label. */
    public function label(): string
    {
        return (string) $this->validated()[$this->field()];
    }

    /**
     * Industry tags to sync, or null to leave them untouched. The web form always sends
     * `industry_ids_present`, so unticking every industry clears them; API clients that omit
     * `industry_ids` keep the existing tags.
     *
     * @return int[]|null
     */
    public function industryIds(): ?array
    {
        if (!$this->tagsIndustries() || (!$this->has('industry_ids') && !$this->boolean('industry_ids_present'))) {
            return null;
        }

        return array_map('intval', $this->validated()['industry_ids'] ?? []);
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->is('api/*') || $this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }

    protected function getRedirectUrl()
    {
        $entry = $this->entry();

        return $entry
            ? route($this->routePrefix() . '.edit', $entry)
            : route($this->routePrefix() . '.create');
    }
}
