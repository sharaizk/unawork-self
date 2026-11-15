<?php

namespace App\Http\Requests\Catalog;

use App\Models\Industry;
use Illuminate\Database\Eloquent\Model;

class StoreIndustryRequest extends CatalogEntryRequest
{
    protected function modelClass(): string
    {
        return Industry::class;
    }

    protected function routePrefix(): string
    {
        return 'industries';
    }

    protected function entry(): ?Model
    {
        return null;
    }
}
