<?php

namespace App\Http\Resources;

use App\Services\Matching\PodMatchPresenter;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape of a persisted POD match (UNA-013): ids plus PodMatchPresenter's translated, ordered
 * criterion entries. Load `reasons` before wrapping a collection to avoid N+1 queries.
 *
 * @mixin \App\Models\PodMatch
 */
class PodMatchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'pod_id' => $this->pod_id,
            'pod_worker_requirement_id' => $this->pod_worker_requirement_id,
            'user_id' => $this->user_id,
        ] + app(PodMatchPresenter::class)->present($this->resource);
    }
}
