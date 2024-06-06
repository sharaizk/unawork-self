<?php

namespace App\Http\Controllers;

use App\Models\Skills;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SkillsController extends Controller
{
    public function index(): JsonResponse
    {   
        $this->authorize('viewAny', Skills::class);
        $skills = Skills::all();
        return response()->json($skills);
    }

    public function store(Request $request): JsonResponse
    {   
        $this->authorize('create', Skills::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $skills = Skills::create($validated);

        return response()->json($skills, Response::HTTP_CREATED);
    }

    public function show(Skills $skills): JsonResponse
    {   
        $this->authorize('view', $skills);
        return response()->json($skills);
    }

    public function update(Request $request, Skills $skills): JsonResponse
    {   
        $this->authorize('update', $skills);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $skills->update($validated);

        return response()->json($skills);
    }

    public function destroy(Skills $skills): JsonResponse
    {   
        $this->authorize('delete', $skills);

        $skills->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
