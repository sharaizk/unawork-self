<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PayHere\Models\Contracts\PayHereCustomer;

/**
 * A PayHere checkout started by UnaWork (plan purchase or invoice payment).
 *
 * Holds the authoritative expected amount/currency and target records. It also acts
 * as the "customer" for the package's form builder, so custom_1 sent to PayHere is
 * this record's id and customer details come from the payer.
 */
class PayHereCheckout extends Model implements PayHereCustomer
{
    public const TYPE_PLAN = 'plan';
    public const TYPE_INVOICE = 'invoice';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVIEW = 'review';

    protected $table = 'payhere_checkouts';

    protected $fillable = [
        'order_id',
        'type',
        'status',
        'user_id',
        'client_id',
        'workspace_id',
        'plan_id',
        'invoice_id',
        'frequency',
        'coupon_id',
        'amount',
        'currency',
        'mode',
        'merchant_id',
        'payhere_payment_id',
        'status_code',
        'payment_method',
        'status_message',
        'failure_reason',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status_code' => 'integer',
        'processed_at' => 'datetime',
    ];

    public static function newOrderId(): string
    {
        return 'UNW' . strtoupper((string) Str::ulid());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isPlan(): bool
    {
        return $this->type === self::TYPE_PLAN;
    }

    public function isInvoice(): bool
    {
        return $this->type === self::TYPE_INVOICE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * The person paying: the logged-in user, otherwise the invoice's client.
     */
    public function payer(): ?Model
    {
        return $this->user ?? $this->client;
    }

    public function title(): string
    {
        if ($this->isPlan()) {
            return (string) ($this->plan->name ?? __('Plan'));
        }

        return __('Invoice Payment');
    }

    public function payHereFirstName(): ?string
    {
        $name = trim((string) ($this->payer()->name ?? ''));

        return $name === '' ? null : Str::before($name, ' ');
    }

    public function payHereLastName(): ?string
    {
        $name = trim((string) ($this->payer()->name ?? ''));
        $last = trim(Str::after($name, ' '));

        return $last === '' ? ($name === '' ? null : $name) : $last;
    }

    public function payHereEmail(): ?string
    {
        return $this->payer()->email ?? null;
    }

    public function payHerePhone(): ?string
    {
        return null;
    }

    public function payHereAddress(): ?string
    {
        return null;
    }

    public function payHereCity(): ?string
    {
        return null;
    }

    public function payHereCountry(): ?string
    {
        return null;
    }
}
