<?php

namespace App\Http\Requests\Matching;

use App\Enums\Matching\MatchReviewAction;
use App\Models\Pod;
use App\Models\PodMatchReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * A business decision on a match review (UNA-018). Only `action` and `reason` are read; user, POD,
 * membership, actor, state or score values in the input are never used (everything else comes
 * from the route-bound, authorized models and the session user).
 */
class DecideMatchReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pod = $this->route('pod');
        $review = $this->route('review');
        if (!$pod instanceof Pod || !$review instanceof PodMatchReview) {
            return false;
        }
        // A review of another POD is "not found" here, not merely forbidden.
        abort_unless((int) $review->membership?->pod_id === (int) $pod->id, 404);

        return (bool) $this->user()?->can('decideMatchReview', [$pod, $review]);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', new Enum(MatchReviewAction::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['action' => __('decision'), 'reason' => __('reason')];
    }
}
