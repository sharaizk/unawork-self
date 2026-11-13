<?php

namespace App\Http\Requests\PayHere;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Invoice checkout. Invoices can be paid by workspace members, the invoice's client
 * (client guard) or anyone holding the public invoice link, so ownership is checked
 * in the controller against the workspace slug rather than here.
 */
class InvoiceCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
