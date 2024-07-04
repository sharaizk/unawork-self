<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = ProjectCategory::all();
        return response()->json($categories, Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = ProjectCategory::create($validatedData);
        return response()->json($category, Response::HTTP_CREATED);
    }

    public function show(ProjectCategory $projectCategory): JsonResponse
    {
        return response()->json($projectCategory, Response::HTTP_OK);
    }

    public function update(Request $request, ProjectCategory $projectCategory): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $projectCategory->update($validatedData);
        return response()->json($projectCategory, Response::HTTP_OK);
    }

    public function destroy(ProjectCategory $projectCategory): JsonResponse
    {
        $projectCategory->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
