<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\News\StoreRequest;
use App\Http\Requests\News\UpdateRequest;
use App\Models\News;
use App\Services\NewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Utility;


class NewsController extends Controller
{
    protected $newsService;

    public function __construct(NewsService $newsService)
    {
        $this->newsService = $newsService;
    }

    public function index(): JsonResponse
    {
        $news = News::all();
        return response()->json($news);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth('sanctum')->user()->id;

        try {
            $news = $this->newsService->store($data);   
            return response()->json($news, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $news = News::findOrFail($id);
        
        try {
            $news = $this->newsService->update($data, $news);   
            return response()->json($news, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function show(int $id): JsonResponse
    {
        $news = News::findOrFail($id);
        return response()->json($news, 201);

    }

    public function destroy(int $id): JsonResponse
    {
        $news = News::find($id);

        try {
            $this->newsService->destroy($news);   
            return response()->json(['message' => "success"], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}