<?php

namespace App\Http\Controllers;

use App\Models\Pod;
use App\Services\Matching\PodRecommendationDismissalService;
use App\Services\Matching\PodRecommendationService;
use App\Services\Profile\ProfileCompletionService;
use Illuminate\Http\Request;

/**
 * Employee POD recommendations (UNA-017). Reads stored matches through PodRecommendationService;
 * never scores, queues matching or writes memberships (applying uses the existing api.pods.join).
 */
class PodRecommendationController extends Controller
{
    public function __construct(
        private PodRecommendationService $recommendations,
        private PodRecommendationDismissalService $dismissals,
    ) {
    }

    public function index(Request $request, ProfileCompletionService $completion)
    {
        $employee = $request->user();
        abort_unless($this->recommendations->canView($employee), 403);

        $ranked = $this->recommendations->rankedMatches($employee);
        $recommendations = $this->recommendations->paginate($employee, max(1, (int) $request->query('page', 1)));
        $summary = $this->recommendations->summary($employee, $ranked);
        $emptyReason = $summary['count'] === 0 ? $this->recommendations->emptyReason($employee) : null;

        return view('pod.recommendations.index', compact('employee', 'recommendations', 'summary', 'emptyReason'));
    }

    public function dismiss(Request $request, Pod $pod)
    {
        $employee = $request->user();
        abort_unless($this->recommendations->canView($employee), 403);

        $this->dismissals->dismiss($employee, $pod);
        $message = __('Recommendation dismissed. You can still find this POD under Browse all PODs.');

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => $message, 'pod_id' => $pod->id]);
        }

        return redirect()->route('pod-recommendations.index')->with('success', $message);
    }
}
