<?php

namespace App\Http\Controllers;

use App\Models\Skills;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SkillsController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Skills::class, 'skill');
    }

    public function index(): JsonResponse
    {   
        $this->authorize('viewAny', Skill::class);
        $skills = Skills::all();
        return response()->json($skills);
    }

    public function store(Request $request): JsonResponse
    {   
        $this->authorize('create', Skill::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $skill = Skills::create($validated);

        return response()->json($skill, Response::HTTP_CREATED);
    }

    public function show(Skills $skill): JsonResponse
    {   
        $this->authorize('view', $skill);
        return response()->json($skill);
    }

    public function update(Request $request, Skills $skill): JsonResponse
    {   
        $this->authorize('update', $skill);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $skill->update($validated);

        return response()->json($skill);
    }

    public function destroy(Skills $skill): JsonResponse
    {   
        $this->authorize('delete', $skill);
        
        $skill->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
