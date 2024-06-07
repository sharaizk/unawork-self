<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pods;
use App\Models\Utility;
use Illuminate\Http\JsonResponse;

class PodsController extends Controller
{
    public function index(): JsonResponse
    {
        $pods = Pods::with('skills')->get();

        return response()->json(['pods' => $pods]);
    }

    public function store(Request $request): JsonResponse
    {   
        $data = $request->validate([
            'title' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string|max:2048'
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

        return response()->json(['pod' => $pod], 201);
    }

    public function show(int $id): JsonResponse
    {   
        $pod = Pods::findOrFail($id);

        return response()->json(['pod' => $pod]);
    }

    public function update(Request $request, int $id): JsonResponse
    {   
        $pod = Pods::findOrFail($id);

        $data = $request->validate([
            'title' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string|max:2048'
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

        return response()->json(['pod' => $pod]);
    }

    public function destroy(int $id): JsonResponse
    {   
        $pod = Pods::findOrFail($id);
        
        $pod->delete();

        return response()->json(null, 204);
    }
}
