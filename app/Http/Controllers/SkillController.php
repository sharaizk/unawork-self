<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Requests\Catalog\StoreSkillRequest;
use App\Http\Requests\Catalog\UpdateSkillRequest;
use App\Models\Skill;
use Illuminate\Http\Request;

/** Administrator catalog: global skills selected by employees, PODs and job vacancies. */
class SkillController extends CatalogController
{
    protected function model(): string
    {
        return Skill::class;
    }

    protected function labels(): array
    {
        return [
            'route' => 'skills',
            'param' => 'skill',
            'title' => __('Skills'),
            'singular' => __('Skill'),
            'description' => __('Skills employees rate on their profile and PODs and job vacancies require.'),
            'icon' => 'bulb',
            'field_label' => __('Skill name'),
            'placeholder' => __('e.g. Kubernetes'),
            'search' => __('Search skills'),
            'empty' => __('No skills found.'),
            'add' => __('Add Skill'),
            'edit' => __('Edit Skill'),
            'usage_label' => __('Assignments'),
            'usage_help' => __('Employees with this skill, plus PODs and job vacancies that require it.'),
            'created' => __('Skill created successfully.'),
            'updated' => __('Skill updated successfully.'),
            'deleted' => __('Skill deleted successfully.'),
            'in_use' => 'This skill cannot be deleted because it is currently used by :count records.',
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

    public function store(StoreSkillRequest $request)
    {
        return $this->created($request->label(), $request->industryIds());
    }

    public function edit(Request $request, Skill $skill)
    {
        return $this->form($request, $skill);
    }

    public function update(UpdateSkillRequest $request, Skill $skill)
    {
        return $this->renamed($skill, $request->label(), $request->industryIds());
    }

    public function destroy(Request $request, Skill $skill)
    {
        return $this->deleted($request, $skill);
    }
}
