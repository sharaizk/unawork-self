<?php

namespace App\Http\Requests\PayHere;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;

class PlanCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string'],
            'payhere_payment_frequency' => ['nullable', 'in:monthly,annual'],
            'coupon' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function planId(): ?int
    {
        try {
            $id = Crypt::decrypt($this->validated('plan_id'));
        } catch (DecryptException) {
            return null;
        }

        return is_numeric($id) ? (int) $id : null;
    }

    public function frequency(): string
    {
        return $this->validated('payhere_payment_frequency') === 'annual' ? 'annual' : 'monthly';
    }

    public function couponCode(): ?string
    {
        return $this->validated('coupon');
    }
}
