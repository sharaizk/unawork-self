<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SkillController extends Controller
{
    public function index(): JsonResponse
    {   
        $this->authorize('viewAny', Skill::class);
        $skills = Skill::all();
        return response()->json($skills);
    }

    public function store(Request $request): JsonResponse
    {   
        // $this->authorize('create', Skill::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $skill = Skill::create($validated);

        return response()->json($skill, Response::HTTP_CREATED);
    }

    public function show(Skill $skill): JsonResponse
    {   
        $this->authorize('view', $skill);
        return response()->json($skill);
    }

    public function update(Request $request, Skill $skill): JsonResponse
    {   
        $this->authorize('update', $skill);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $skill->update($validated);

        return response()->json($skill);
    }

    public function destroy(Skill $skill): JsonResponse
    {   
        $this->authorize('delete', $skill);

        $skill->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
