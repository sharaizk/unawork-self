<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreSkillRequest;
use App\Http\Requests\Catalog\UpdateSkillRequest;
use App\Models\Skill;
use App\Services\Catalog\CatalogEntryInUse;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Global skills catalog API (auth:sanctum). Any authenticated account may read the catalog (it is
 * the reference list profile, POD and vacancy forms select from); creating, renaming and deleting
 * are administrator-only through SkillPolicy, enforced by the FormRequests and authorize().
 */
class SkillController extends Controller
{
    public function __construct(private CatalogService $catalog)
    {
    }

    public function index(): JsonResponse
    {
        // Optional ?industry= narrows to that industry's skills plus general (untagged) skills.
        return response()->json(Skill::query()->forIndustry(request()->integer('industry') ?: null)->alphabetical()->get());
    }

    public function store(StoreSkillRequest $request): JsonResponse
    {
        $skill = $this->catalog->create(Skill::class, $request->label(), $request->industryIds());

        return response()->json($skill, Response::HTTP_CREATED);
    }

    public function show(Skill $skill): JsonResponse
    {
        return response()->json($skill);
    }

    public function update(UpdateSkillRequest $request, Skill $skill): JsonResponse
    {
        return response()->json($this->catalog->rename($skill, $request->label(), $request->industryIds()));
    }

    public function destroy(Skill $skill): JsonResponse
    {
        $this->authorize('delete', $skill);

        try {
            $this->catalog->delete($skill, 'This skill cannot be deleted because it is currently used by :count records.');
        } catch (CatalogEntryInUse $e) {
            return response()->json(['message' => $e->getMessage(), 'usage' => $e->usage], Response::HTTP_CONFLICT);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
