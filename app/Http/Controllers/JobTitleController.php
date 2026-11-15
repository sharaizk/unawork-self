<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Requests\Catalog\StoreJobTitleRequest;
use App\Http\Requests\Catalog\UpdateJobTitleRequest;
use App\Models\JobTitle;
use Illuminate\Http\Request;

/**
 * Administrator catalog: professional roles (job titles such as "Backend Developer"), used as an
 * employee's current job title, preferred roles and experience titles. Labelled "Professional
 * Roles" in the UI; unrelated to Spatie authorization roles or account personas.
 */
class JobTitleController extends CatalogController
{
    protected function model(): string
    {
        return JobTitle::class;
    }

    protected function labels(): array
    {
        return [
            'route' => 'job-titles',
            'param' => 'job_title',
            'title' => __('Professional Roles'),
            'singular' => __('Professional Role'),
            'description' => __('Job titles employees use for their current role, preferred roles and experience.'),
            'icon' => 'id',
            'field_label' => __('Role title'),
            'placeholder' => __('e.g. Backend Developer'),
            'search' => __('Search professional roles'),
            'empty' => __('No professional roles found.'),
            'add' => __('Add Professional Role'),
            'edit' => __('Edit Professional Role'),
            'usage_label' => __('Profiles'),
            'usage_help' => __('Profiles using this role as their current title or a preferred role, plus experience records and company worker requirements.'),
            'created' => __('Professional role created successfully.'),
            'updated' => __('Professional role updated successfully.'),
            'deleted' => __('Professional role deleted successfully.'),
            'in_use' => 'This professional role cannot be deleted because it is currently used by :count records.',
        ];
    }

    public function index(Request $request)
    {
        return $this->listing($request);
    }

    public function create(Request $request)
    {
        return $this->form($request);
    }

    public function store(StoreJobTitleRequest $request)
    {
        return $this->created($request->label(), $request->industryIds());
    }

    public function edit(Request $request, JobTitle $jobTitle)
    {
        return $this->form($request, $jobTitle);
    }

    public function update(UpdateJobTitleRequest $request, JobTitle $jobTitle)
    {
        return $this->renamed($jobTitle, $request->label(), $request->industryIds());
    }

    public function destroy(Request $request, JobTitle $jobTitle)
    {
        return $this->deleted($request, $jobTitle);
    }
}
