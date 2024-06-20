<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\News\StoreRequest;
use App\Http\Requests\News\UpdateRequest;
use App\Models\News;
use App\Services\NewsService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    protected $newsService;

    public function __construct(NewsService $newsService)
    {
        $this->newsService = $newsService;
    }

    public function index()
    {
        $news = News::all();
        return response()->json($news);
    }

    public function store(StoreRequest $request)
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

    public function update(UpdateRequest $request, $id)
    {
        $data = $request->validated();

        try {
            $news = $this->newsService->update($data, $id);   
            return response()->json($news, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function show($id)
    {
        return News::findOrFail($id);
    }

    public function destroy($id)
    {
        try {
            $this->newsService->destroy($id);   
            return response()->json(['message' => "success"], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}