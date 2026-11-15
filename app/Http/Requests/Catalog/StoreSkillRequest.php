<?php

namespace App\Http\Requests\Catalog;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;

class StoreSkillRequest extends CatalogEntryRequest
{
    protected function modelClass(): string
    {
        return Skill::class;
    }

    protected function routePrefix(): string
    {
        return 'skills';
    }

    protected function entry(): ?Model
    {
        return null;
    }
}
