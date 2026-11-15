<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pod;
use App\Models\User;
use App\Models\Workspace;
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
        $pods = Pod::published()->get(); // UNA-011: drafts are never public

        return PodResource::collection($pods);
    }

    public function store(StorePodRequest $request): JsonResponse | PodResource
    {   
        $data = $request->validated();
        $data['created_by'] = auth('sanctum')->user()->id;
        if (auth('sanctum')->user()->isAdministrator()) {
            return response()->json([
                'message' => "superadmin can't create pods!"
            ], 400);
        }
        
        try {
            // UNA-011: creates a draft through the hardened PodService path; publishing is separate.
            unset($data['created_by']);
            $pod = $this->podService->createDraft($request->user(), $data, $request->file('image'));
            return new PodResource($pod);
        } catch (\App\Services\Pod\UnauthorizedPodMutation $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function show(int $id): PodResource
    {   
        $pod = Pod::findOrFail($id);
        // UNA-011: a draft is visible only to the company that owns it.
        abort_unless($pod->isPublished() || (int) optional(auth('sanctum')->user())->id === (int) $pod->created_by, 404);

        return new PodResource($pod);
    }

    public function update(UpdatePodRequest $request, int $id): JsonResponse | PodResource
    {   
        $pod = Pod::findOrFail($id);
        $this->authorize('update', $pod);
        $data = $request->validated();
        unset($data['image']);

        try {
            // Same hardened path as the guided setup; a published POD cannot lose a complete section.
            $updatedPod = $this->podService->updateDraft($pod, $request->user(), $data, $request->file('image'));
            return new PodResource($updatedPod);
        } catch (\App\Services\Pod\PodPublicationRefused|\App\Services\Pod\UnauthorizedPodMutation $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(int $id): JsonResponse
    {   
        $pod = Pod::findOrFail($id);
        $this->authorize('delete', $pod);

        $this->podService->destroyPod($pod);

        return response()->json(null, 204);
    }
}
