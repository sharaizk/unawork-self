<?php

namespace App\Services\Pod;

use Illuminate\Http\Request;

/** Only the POD-owning company may create, configure or publish a POD (UNA-011). */
class UnauthorizedPodMutation extends \DomainException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Only the company that owns this POD can change it.'));
    }

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage()], 403);
        }

        abort(403, $this->getMessage());
    }
}
