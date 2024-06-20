<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pod;
use App\Models\Utility;
use App\Services\PodService;
use App\Http\Requests\Pods\StorePodRequest;
use App\Http\Requests\Pods\UpdatePodRequest;
use App\Http\Resources\PodResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PodController extends Controller
{
    protected $podService;

    public function __construct(PodService $podService)
    {
        $this->podService = $podService;
    }
    
    public function index(): AnonymousResourceCollection
    {
        $pods = Pod::all();

        return PodResource::collection($pods);
    }

    public function store(StorePodRequest $request): JsonResponse | PodResource
    {   
        $data = $request->validated();
        $data['created_by'] = auth('sanctum')->user()->id;
        
        try {
            $pod = $this->podService->createPod($data);
            return new PodResource($pod);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function show(int $id): PodResource
    {   
        $pod = Pod::findOrFail($id);

        return new PodResource($pod);
    }

    public function update(UpdatePodRequest $request, int $id): JsonResponse | PodResource
    {   
        $pod = Pod::findOrFail($id);
        $this->authorize('update', $pod);
        $data = $request->validated();
    
        try {
            $updatedPod = $this->podService->updatePod($pod, $data);
            return new PodResource($updatedPod);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(int $id): JsonResponse
    {   
        $pod = Pod::findOrFail($id);
        
        $pod->delete();

        return response()->json(null, 204);
    }
}
