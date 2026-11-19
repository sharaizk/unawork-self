<?php

namespace App\Http\Controllers;

use App\Exceptions\PodDocument\PodMaterialVersionChangedException;
use App\Models\Pod;
use App\Models\PodDocument;
use App\Services\PodDocument\PodLearningProgressService;
use Illuminate\Http\Request;

/**
 * Controller for Outer Circle POD learning materials flow (UNA-023).
 */
class PodLearningController extends Controller
{
    public function __construct(
        private PodLearningProgressService $learningService,
    ) {
    }

    /**
     * Learning overview: overall progress, mandatory vs optional materials, and resume CTA.
     */
    public function index(Request $request, Pod $pod)
    {
        $this->authorize('accessLearning', $pod);

        $progress = $this->learningService->breakdown($request->user(), $pod);
        $currentWorkspace = $pod->workspace ?? $request->user()->currentWorkspace;

        return view('pod.learning.index', compact('pod', 'progress', 'currentWorkspace'));
    }

    /**
     * Resume learning flow: redirects to the next incomplete mandatory material item.
     */
    public function resume(Request $request, Pod $pod)
    {
        $this->authorize('accessLearning', $pod);

        $next = $this->learningService->nextIncompleteMandatory($request->user(), $pod);

        if ($next) {
            return redirect()->route('pods.learning.show', [$pod, $next]);
        }

        $items = $this->learningService->getOrderedPublishedItems($pod);
        if ($items->isNotEmpty()) {
            // If all mandatory items are complete, check for first incomplete optional item or first item
            $firstIncomplete = $items->first(function ($doc) use ($request) {
                return !$this->learningService->isDocumentCompletedByUser($doc, $request->user());
            });

            if ($firstIncomplete) {
                return redirect()->route('pods.learning.show', [$pod, $firstIncomplete]);
            }
        }

        return redirect()->route('pods.learning.index', $pod)
            ->with('info', __('All mandatory materials have already been completed!'));
    }

    /**
     * View a specific published learning material item in the learning flow.
     */
    public function show(Request $request, Pod $pod, PodDocument $document)
    {
        if ((int) $document->pod_id !== (int) $pod->id) {
            abort(404);
        }

        $this->authorize('viewLearning', $document);

        $version = $document->publishedVersion();
        if (!$version || !$version->isUploadReady()) {
            abort(404);
        }

        $isCompleted = $this->learningService->isDocumentCompletedByUser($document, $request->user());
        $completion = $this->learningService->getCompletionRecord($document, $request->user());
        $progress = $this->learningService->breakdown($request->user(), $pod);

        $orderedItems = $this->learningService->getOrderedPublishedItems($pod);
        $currentIndex = $orderedItems->search(fn ($d) => (int) $d->id === (int) $document->id);
        $prevItem = ($currentIndex !== false && $currentIndex > 0) ? $orderedItems->get($currentIndex - 1) : null;
        $nextItem = ($currentIndex !== false && $currentIndex < $orderedItems->count() - 1) ? $orderedItems->get($currentIndex + 1) : null;
        $currentWorkspace = $pod->workspace ?? $request->user()->currentWorkspace;

        return view('pod.learning.show', compact(
            'pod',
            'document',
            'version',
            'isCompleted',
            'completion',
            'progress',
            'prevItem',
            'nextItem',
            'currentWorkspace'
        ));
    }

    /**
     * Mark a published learning material item as completed for the current user.
     */
    public function complete(Request $request, Pod $pod, PodDocument $document)
    {
        if ((int) $document->pod_id !== (int) $pod->id) {
            abort(404);
        }

        $this->authorize('completeLearning', $document);

        $request->validate([
            'version_id' => ['required', 'integer'],
        ]);

        try {
            $this->learningService->markCompleted(
                $request->user(),
                $pod,
                $document,
                (int) $request->input('version_id')
            );
        } catch (PodMaterialVersionChangedException $e) {
            return redirect()->route('pods.learning.show', [$pod, $document])
                ->with('error', __($e->getMessage()))
                ->with('warning', __($e->getMessage()));
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        } catch (\DomainException $e) {
            return redirect()->route('pods.learning.show', [$pod, $document])
                ->with('error', __($e->getMessage()));
        }

        $next = $this->learningService->nextIncompleteMandatory($request->user(), $pod);

        if ($next) {
            return redirect()->route('pods.learning.show', [$pod, $next])
                ->with('success', __('Material completed! Directing to the next required item.'));
        }

        return redirect()->route('pods.learning.index', $pod)
            ->with('success', __('Congratulations! All mandatory learning materials for this POD are now completed.'));
    }
}
