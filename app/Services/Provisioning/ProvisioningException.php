<?php

namespace App\Services\Provisioning;

use Illuminate\Http\Request;

/**
 * Expected provisioning outcomes that are not server errors (UNA-012). Rendered as a field error
 * (422) or a conflict (409) for JSON, and as a redirect back with the message for HTML. Messages
 * are translated and never include internal details.
 */
abstract class ProvisioningException extends \DomainException
{
    /** HTTP status for JSON responses. */
    abstract public function status(): int;

    /** Form field the message belongs to, or null for a general message. */
    public function field(): ?string
    {
        return null;
    }

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            $body = ['message' => $this->getMessage()];
            if ($this->field()) {
                $body['errors'] = [$this->field() => [$this->getMessage()]];
            }

            return response()->json($body, $this->status());
        }

        $redirect = redirect()->back()->withInput($request->except(['password', 'password_confirmation', 'userpassword']));

        return $this->field()
            ? $redirect->withErrors([$this->field() => $this->getMessage()])
            : $redirect->with('error', $this->getMessage());
    }
}
