<?php

namespace App\Http\Requests\Catalog;

use App\Models\JobTitle;
use Illuminate\Database\Eloquent\Model;

class UpdateJobTitleRequest extends CatalogEntryRequest
{
    protected function modelClass(): string
    {
        return JobTitle::class;
    }

    protected function routePrefix(): string
    {
        return 'job-titles';
    }

    protected function entry(): ?Model
    {
        return $this->route('job_title');
    }
}
