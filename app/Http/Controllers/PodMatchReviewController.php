<?php

namespace App\Http\Controllers;

use App\Enums\Matching\MatchReviewAction;
use App\Http\Requests\Matching\DecideMatchReviewRequest;
use App\Models\Pod;
use App\Models\PodMatchReview;
use App\Services\Matching\MatchReviewConflict;
use App\Services\Matching\MatchReviewNotApprovable;
use App\Services\Matching\PodMatchReviewDecisionService;
use App\Services\Matching\PodMatchReviewService;
use App\Services\Membership\InvalidStateTransition;
use Illuminate\Http\Request;

/**
 * Business match-review queue (UNA-018). Reads through PodMatchReviewService and delegates every
 * decision to PodMatchReviewDecisionService; never writes state, exclusions or notifications.
 */
class PodMatchReviewController extends Controller
{
    public function __construct(
        private PodMatchReviewService $reviews,
        private PodMatchReviewDecisionService $decisions,
    ) {
    }

    public function index(Request $request, Pod $pod)
    {
        $this->authorize('viewMatchReviews', $pod);

        $ranked = $this->reviews->ranked($pod);
        $items = $this->reviews->paginate($pod, max(1, (int) $request->query('page', 1)), null, $ranked);
        $summary = $this->reviews->summary($ranked);

        return view('pod.match_reviews.index', compact('pod', 'items', 'summary'));
    }

    public function decide(DecideMatchReviewRequest $request, Pod $pod, PodMatchReview $review)
    {
        $action = MatchReviewAction::from($request->validated('action'));

        try {
            $this->decisions->decide($pod, $review, $action, $request->user(), $request->validated('reason'));
        } catch (MatchReviewConflict $e) {
            return $this->respond($request, $pod, 409, 'already_reviewed', $e->getMessage());
        } catch (MatchReviewNotApprovable|InvalidStateTransition $e) {
            return $this->respond($request, $pod, 422, 'not_approvable', $e->getMessage());
        }

        $message = match ($action) {
            MatchReviewAction::APPROVE => __('Approved. The employee is now in the Outer Circle.'),
            MatchReviewAction::REJECT => __('Match rejected.'),
            MatchReviewAction::EXCLUDE => __('Match rejected and excluded from future matching for this POD.'),
        };

        return $this->respond($request, $pod, 200, 'success', $message);
    }

    private function respond(Request $request, Pod $pod, int $status, string $code, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => $code, 'message' => $message], $status);
        }

        return redirect()->route('pods.match-reviews.index', $pod)->with($status === 200 ? 'success' : 'error', $message);
    }
}
