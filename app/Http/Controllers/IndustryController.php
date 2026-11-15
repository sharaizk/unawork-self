<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Requests\Catalog\StoreIndustryRequest;
use App\Http\Requests\Catalog\UpdateIndustryRequest;
use App\Models\Industry;
use Illuminate\Http\Request;

/** Administrator catalog: industries used to classify company workspaces. */
class IndustryController extends CatalogController
{
    protected function model(): string
    {
        return Industry::class;
    }

    protected function labels(): array
    {
        return [
            'route' => 'industries',
            'param' => 'industry',
            'title' => __('Industries'),
            'singular' => __('Industry'),
            'description' => __('Industries companies can choose to classify their workspace.'),
            'icon' => 'building-factory',
            'field_label' => __('Industry name'),
            'placeholder' => __('e.g. Renewable Energy'),
            'search' => __('Search industries'),
            'empty' => __('No industries found.'),
            'add' => __('Add Industry'),
            'edit' => __('Edit Industry'),
            'usage_label' => __('Workspaces'),
            'usage_help' => __('Company workspaces and employee profiles using this industry. Linked skills and roles are not counted.'),
            'created' => __('Industry created successfully.'),
            'updated' => __('Industry updated successfully.'),
            'deleted' => __('Industry deleted successfully.'),
            'in_use' => 'This industry cannot be deleted because it is currently used by :count records.',
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

    public function store(StoreIndustryRequest $request)
    {
        return $this->created($request->label());
    }

    public function edit(Request $request, Industry $industry)
    {
        return $this->form($request, $industry);
    }

    public function update(UpdateIndustryRequest $request, Industry $industry)
    {
        return $this->renamed($industry, $request->label());
    }

    public function destroy(Request $request, Industry $industry)
    {
        return $this->deleted($request, $industry);
    }
}
