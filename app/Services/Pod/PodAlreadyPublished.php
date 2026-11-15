<?php

namespace App\Services\Pod;

use App\Models\Pod;
use Illuminate\Http\Request;

/** A repeated publish request for a POD that is already published (UNA-011). Nothing changes. */
class PodAlreadyPublished extends \DomainException
{
    public function __construct(public readonly Pod $pod)
    {
        parent::__construct(__('This POD is already published.'));
    }

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage(), 'status' => 'published', 'pod_url' => route('landing.pods.show', $this->pod)], 409);
        }

        return redirect()->route('pods.index')->with('success', $this->getMessage());
    }
}
