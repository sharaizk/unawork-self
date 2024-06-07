<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pods;
use App\Models\Utility;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\PodResource;

class PodsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $pods = Pods::with('skills')->get();

        return PodResource::collection($pods);
    }

    public function store(Request $request): JsonResponse | PodResource
    {   
        $data = $request->validate([
            'title' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string|max:2048',
            'skills' => 'nullable|array', // Додаємо валідацію для скілів
            'skills.*' => 'integer|exists:skills,id' // Перевірка, що кожен скіл існує в таблиці skills
        ]);

        //upload image with personal driver settings (for each user)
        if ($request->has('image')) {
            $dir = 'pods-images/';
            $imgName = uniqid() . '.png';
            $path = Utility::upload_file($request, 'image', $imgName, $dir, []);
            if ($path['flag'] == 1) {
                $image = $path['url'];
            } else {
                return response()->json([
                    'message' => $path['msg']
                ], 400);
            }
            $data['image'] = $image;
        }

        $pod = Pods::create($data);

        if (isset($data['skills'])) {
            $pod->skills()->sync($data['skills']);
        }

        return new PodResource($pod);
    }

    public function show(int $id): PodResource
    {   
        $pod = Pods::with('skills')->findOrFail($id);

        return new PodResource($pod);
    }

    public function update(Request $request, int $id): JsonResponse | PodResource
    {   
        $pod = Pods::findOrFail($id);

        $data = $request->validate([
            'title' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string|max:2048',
            'skills' => 'nullable|array', // skills validation
            'skills.*' => 'integer|exists:skills,id' // skills existing
        ]);

        //upload image with personal driver settings (for each user)
        if ($request->has('image')) {
            $dir = 'pods-images/';
            $imgName = uniqid() . '.png';
            $path = Utility::upload_file($request, 'image', $imgName, $dir, []);
            if ($path['flag'] == 1) {
                $image = $path['url'];
            } else {
                return response()->json([
                    'message' => $path['msg']
                ], 400);
            }
            $data['image'] = $image;
        }

        $pod->update($data);

        if (isset($data['skills'])) {
            $pod->skills()->sync($data['skills']);
        }

        return new PodResource($pod);
    }

    public function destroy(int $id): JsonResponse
    {   
        $pod = Pods::findOrFail($id);
        
        $pod->delete();

        return response()->json(null, 204);
    }
}
