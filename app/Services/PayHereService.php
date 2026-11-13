<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\PayHereCheckout;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\Utility;
use App\Models\Workspace;
use App\Services\PayHere\PayHereCheckoutException;
use App\Services\PayHere\PayHereCredentials;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PayHere\Enums\PaymentStatus;
use PayHere\PayHere;
use Throwable;

/**
 * PayHere integration for UnaWork.
 *
 * Flow: start*Checkout() persists a pending PayHereCheckout with the authoritative
 * amount/currency/targets → checkoutResponse() renders the package's auto-submitting
 * form → PayHere calls handleNotification() server-to-server → the signature is
 * verified with the right merchant secret (admin for plans, workspace for invoices)
 * and the payment is finalised exactly once inside a transaction.
 *
 * Browser return/cancel pages only read the checkout status.
 */
class PayHereService
{
    public const PAYMENT_TYPE = 'Payhere';

    // ---------------------------------------------------------------------
    // Credentials
    // ---------------------------------------------------------------------

    public function adminCredentials(): PayHereCredentials
    {
        return PayHereCredentials::fromSettings(Utility::getAdminPaymentSetting());
    }

    public function workspaceCredentials(int $workspaceId): PayHereCredentials
    {
        return PayHereCredentials::fromSettings(Utility::getPaymentSetting($workspaceId));
    }

    public function credentialsFor(PayHereCheckout $checkout): PayHereCredentials
    {
        return $checkout->isPlan()
            ? $this->adminCredentials()
            : $this->workspaceCredentials((int) $checkout->workspace_id);
    }

    // ---------------------------------------------------------------------
    // Starting a checkout
    // ---------------------------------------------------------------------

    /**
     * @throws PayHereCheckoutException
     */
    public function startPlanCheckout(User $user, Plan $plan, string $frequency, ?string $couponCode): PayHereCheckout
    {
        $credentials = $this->requireUsable($this->adminCredentials());

        $settings = Utility::getAdminPaymentSetting();
        $currency = $this->supportedCurrency($settings['currency'] ?? null);

        $amount = (float) ($frequency === 'annual' ? $plan->annual_price : $plan->monthly_price);

        $coupon = null;
        if ($couponCode !== null && trim($couponCode) !== '') {
            $coupon = Coupon::where('code', strtoupper(trim($couponCode)))->where('is_active', '1')->first();
            if (! $coupon) {
                throw new PayHereCheckoutException(__('This coupon code is invalid or has expired.'));
            }
            if ($coupon->used_coupon() >= $coupon->limit) {
                throw new PayHereCheckoutException(__('This coupon code has expired.'));
            }
            $amount -= ($amount / 100) * $coupon->discount;
        }

        $amount = round(max($amount, 0), 2);

        if ($amount <= 0 && ! $coupon) {
            throw new PayHereCheckoutException(__('The payment amount is invalid.'));
        }

        $checkout = PayHereCheckout::create([
            'order_id' => PayHereCheckout::newOrderId(),
            'type' => PayHereCheckout::TYPE_PLAN,
            'status' => PayHereCheckout::STATUS_PENDING,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'frequency' => $frequency,
            'coupon_id' => $coupon?->id,
            'amount' => $amount,
            'currency' => $currency,
            'mode' => $credentials->mode(),
            'merchant_id' => $credentials->merchantId,
        ]);

        // A 100% coupon: nothing to charge, activate immediately.
        if ($amount <= 0) {
            $this->completeFreePlan($checkout);
        }

        return $checkout->refresh();
    }

    /**
     * @throws PayHereCheckoutException
     */
    public function startInvoiceCheckout(Invoice $invoice, Workspace $workspace, ?User $user, ?Client $client, float $amount): PayHereCheckout
    {
        $credentials = $this->requireUsable($this->workspaceCredentials($workspace->id));
        $currency = $this->supportedCurrency($workspace->currency_code ?: 'USD');

        $amount = round($amount, 2);
        $due = round((float) $invoice->getDueAmount(), 2);

        if ($due <= 0) {
            throw new PayHereCheckoutException(__('This invoice is already paid.'));
        }
        if ($amount <= 0) {
            throw new PayHereCheckoutException(__('The payment amount is invalid.'));
        }
        if ($amount > $due) {
            throw new PayHereCheckoutException(__('The payment amount cannot exceed the remaining invoice balance.'));
        }

        return PayHereCheckout::create([
            'order_id' => PayHereCheckout::newOrderId(),
            'type' => PayHereCheckout::TYPE_INVOICE,
            'status' => PayHereCheckout::STATUS_PENDING,
            'user_id' => $user?->id,
            'client_id' => $client?->id ?? $invoice->client_id,
            'workspace_id' => $workspace->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'currency' => $currency,
            'mode' => $credentials->mode(),
            'merchant_id' => $credentials->merchantId,
        ]);
    }

    /**
     * Render the package's auto-submitting form that posts the customer to PayHere.
     */
    public function checkoutResponse(PayHereCheckout $checkout): Response
    {
        $credentials = $this->requireUsable($this->credentialsFor($checkout));

        $html = $this->withPackageConfig($credentials, [
            'payhere.notify_url' => route('payhere.callback.notify'),
            'payhere.return_url' => route('payhere.callback.return', ['order' => $checkout->order_id]),
            'payhere.cancel_url' => route('payhere.callback.cancel', ['order' => $checkout->order_id]),
        ], function () use ($checkout) {
            return PayHere::builder()
                ->customer($checkout)
                ->orderId($checkout->order_id)
                ->title($checkout->title())
                ->currency($checkout->currency)
                ->amount((float) $checkout->amount)
                ->checkout()
                ->render();
        });

        return response($html)->header('Cache-Control', 'no-store');
    }

    // ---------------------------------------------------------------------
    // Server-to-server notification
    // ---------------------------------------------------------------------

    /**
     * Verify and apply a PayHere notify_url callback. Returns a short outcome code
     * for logging; never throws for bad input.
     */
    public function handleNotification(array $payload): string
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $merchantId = (string) ($payload['merchant_id'] ?? '');
        $amount = (string) ($payload['payhere_amount'] ?? '');
        $currency = (string) ($payload['payhere_currency'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $signature = strtoupper((string) ($payload['md5sig'] ?? ''));

        if ($orderId === '' || strlen($orderId) > 64 || ! preg_match('/^-?\d+$/', $statusCode) || $signature === '') {
            Log::warning('PayHere notification rejected: malformed payload.');

            return 'malformed';
        }

        $checkout = PayHereCheckout::where('order_id', $orderId)->first();
        if (! $checkout) {
            Log::warning('PayHere notification rejected: unknown order.', ['order_id' => $orderId]);

            return 'unknown_order';
        }

        $credentials = $this->credentialsFor($checkout);
        if ($credentials->merchantId === '') {
            Log::error('PayHere notification rejected: credentials not configured.', ['order_id' => $orderId, 'type' => $checkout->type]);

            return 'not_configured';
        }

        $expected = $credentials->notificationSignature($orderId, $amount, $currency, $statusCode);
        if (! hash_equals($credentials->merchantId, $merchantId) || ! hash_equals($expected, $signature)) {
            Log::warning('PayHere notification rejected: merchant or signature mismatch.', ['order_id' => $orderId]);

            return 'invalid_signature';
        }

        $status = (int) $statusCode;
        $amountMatches = number_format((float) $amount, 2, '.', '') === number_format((float) $checkout->amount, 2, '.', '');
        $currencyMatches = strtoupper($currency) === strtoupper($checkout->currency);

        $details = [
            'payhere_payment_id' => mb_substr((string) ($payload['payment_id'] ?? ''), 0, 64) ?: null,
            'status_code' => $status,
            'payment_method' => mb_substr((string) ($payload['method'] ?? ''), 0, 32) ?: null,
            'status_message' => mb_substr((string) ($payload['status_message'] ?? ''), 0, 255) ?: null,
        ];

        try {
            $outcome = DB::transaction(function () use ($checkout, $status, $amountMatches, $currencyMatches, $details) {
                /** @var PayHereCheckout $locked */
                $locked = PayHereCheckout::whereKey($checkout->id)->lockForUpdate()->first();

                if ($locked->isCompleted()) {
                    if ($status === PaymentStatus::Chargeback->value) {
                        Log::warning('PayHere chargeback received for a completed payment; manual action required.', ['order_id' => $locked->order_id]);
                    }

                    return 'duplicate';
                }

                $locked->fill($details);

                if ($status === PaymentStatus::Success->value) {
                    if (! $amountMatches || ! $currencyMatches) {
                        return $this->markForReview($locked, 'amount_or_currency_mismatch');
                    }

                    return $locked->isPlan() ? $this->finalizePlan($locked) : $this->finalizeInvoice($locked);
                }

                $locked->status = match ($status) {
                    PaymentStatus::Pending->value => PayHereCheckout::STATUS_PENDING,
                    PaymentStatus::Cancelled->value => PayHereCheckout::STATUS_CANCELLED,
                    default => PayHereCheckout::STATUS_FAILED,
                };
                $locked->save();

                return 'status_' . $locked->status;
            });
        } catch (Throwable $e) {
            Log::error('PayHere notification processing failed.', [
                'order_id' => $orderId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($outcome === 'completed' && $checkout->isInvoice()) {
            $this->sendInvoiceNotifications($checkout->fresh());
        }

        Log::info('PayHere notification processed.', ['order_id' => $orderId, 'status_code' => $status, 'outcome' => $outcome]);

        return $outcome;
    }

    // ---------------------------------------------------------------------
    // Finalisation (always called inside a transaction with the checkout row locked)
    // ---------------------------------------------------------------------

    private function finalizePlan(PayHereCheckout $checkout): string
    {
        $user = User::find($checkout->user_id);
        $plan = Plan::find($checkout->plan_id);

        if (! $user || ! $plan) {
            return $this->markForReview($checkout, 'plan_or_user_missing');
        }

        if (! Order::where('order_id', $checkout->order_id)->exists()) {
            Order::create([
                'order_id' => $checkout->order_id,
                'name' => $user->name,
                'email' => $user->email,
                'card_number' => null,
                'card_exp_month' => null,
                'card_exp_year' => null,
                'plan_name' => $plan->name,
                'plan_id' => $plan->id,
                'price' => $checkout->amount,
                'price_currency' => $checkout->currency,
                'payment_frequency' => $checkout->frequency,
                'txn_id' => (string) ($checkout->payhere_payment_id ?? ''),
                'payment_type' => self::PAYMENT_TYPE,
                'payment_status' => 'succeeded',
                'receipt' => null,
                'user_id' => $user->id,
            ]);
        }

        if ($checkout->coupon_id && ! UserCoupon::where('order', $checkout->order_id)->exists()) {
            $coupon = Coupon::whereKey($checkout->coupon_id)->lockForUpdate()->first();
            if ($coupon) {
                $userCoupon = new UserCoupon();
                $userCoupon->user = $user->id;
                $userCoupon->coupon = $coupon->id;
                $userCoupon->order = $checkout->order_id;
                $userCoupon->save();

                if ($coupon->used_coupon() >= $coupon->limit) {
                    $coupon->is_active = 0;
                    $coupon->save();
                }
            }
        }

        $assignPlan = $user->assignPlan($plan->id, $checkout->frequency);
        if (empty($assignPlan['is_success'])) {
            throw new \RuntimeException('Plan assignment failed.');
        }

        Utility::referralTransaction([
            'plan_id' => $plan->id,
            'plan_frequency' => $checkout->frequency,
            'plan_price' => Utility::getPlanActualPrice($plan->id, $checkout->frequency),
        ], $user);

        return $this->markCompleted($checkout);
    }

    private function finalizeInvoice(PayHereCheckout $checkout): string
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::whereKey($checkout->invoice_id)->lockForUpdate()->first();

        if (! $invoice || (int) $invoice->workspace_id !== (int) $checkout->workspace_id) {
            return $this->markForReview($checkout, 'invoice_missing');
        }

        $alreadyRecorded = InvoicePayment::where('order_id', $checkout->order_id)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->exists();

        if (! $alreadyRecorded) {
            $due = round((float) $invoice->getDueAmount(), 2);
            $amount = round((float) $checkout->amount, 2);

            if ($amount > $due) {
                // Money was taken but would overpay the invoice (e.g. two checkouts for the
                // same balance). Do not apply it; flag for a manual refund/allocation.
                Log::warning('PayHere invoice payment exceeds remaining balance; marked for review.', [
                    'order_id' => $checkout->order_id,
                    'invoice_id' => $invoice->id,
                ]);

                return $this->markForReview($checkout, 'exceeds_invoice_balance');
            }

            InvoicePayment::create([
                'order_id' => $checkout->order_id,
                'invoice_id' => $invoice->id,
                'currency' => $checkout->currency,
                'amount' => $amount,
                'txn_id' => (string) ($checkout->payhere_payment_id ?? ''),
                'payment_type' => self::PAYMENT_TYPE,
                'payment_status' => 'approved',
                'receipt' => '',
                'client_id' => $checkout->client_id,
                'user_id' => $checkout->user_id ?? 0,
            ]);

            $invoice->status = round($due - $amount, 2) <= 0 ? 'paid' : 'partialy paid';
            $invoice->save();
        }

        return $this->markCompleted($checkout);
    }

    private function completeFreePlan(PayHereCheckout $checkout): void
    {
        DB::transaction(function () use ($checkout) {
            $locked = PayHereCheckout::whereKey($checkout->id)->lockForUpdate()->first();
            if (! $locked->isCompleted()) {
                $this->finalizePlan($locked);
            }
        });

        // Mirrors the previous free-coupon behaviour: stop any other gateway subscription.
        $user = User::find($checkout->user_id);
        if ($user && ! empty($user->payment_subscription_id)) {
            try {
                $user->cancel_subscription($user->id);
            } catch (Throwable $e) {
                Log::warning('Could not cancel previous subscription after free PayHere plan activation.', [
                    'user_id' => $user->id,
                    'exception' => get_class($e),
                ]);
            }
        }
    }

    private function markCompleted(PayHereCheckout $checkout): string
    {
        $checkout->status = PayHereCheckout::STATUS_COMPLETED;
        $checkout->failure_reason = null;
        $checkout->processed_at = now();
        $checkout->save();

        return 'completed';
    }

    private function markForReview(PayHereCheckout $checkout, string $reason): string
    {
        $checkout->status = PayHereCheckout::STATUS_REVIEW;
        $checkout->failure_reason = $reason;
        $checkout->processed_at = now();
        $checkout->save();

        Log::warning('PayHere checkout needs manual review.', ['order_id' => $checkout->order_id, 'reason' => $reason]);

        return 'review';
    }

    // ---------------------------------------------------------------------
    // Invoice notifications (Slack, Telegram, configured webhook) — sent once,
    // after the transaction commits, only when this call completed the payment.
    // ---------------------------------------------------------------------

    private function sendInvoiceNotifications(PayHereCheckout $checkout): void
    {
        $invoice = Invoice::find($checkout->invoice_id);
        $workspace = Workspace::find($checkout->workspace_id);
        if (! $invoice || ! $workspace) {
            return;
        }

        $settings = Utility::getPaymentSetting($workspace->id);
        $project = Project::find($invoice->project_id);
        $client = Client::find($invoice->client_id);

        $uArr = [
            'project_name' => $project->name ?? '',
            'company_name' => $project ? (User::find($project->created_by)->name ?? '') : '',
            'invoice_id' => Utility::invoiceNumberFormat($invoice->id),
            'client_name' => $client->name ?? '',
            'total_amount' => $invoice->getDueAmounts($invoice->id),
            'paid_amount' => $checkout->amount,
        ];

        $module = 'Invoice Status Updated';

        if (($settings['invoicest_notificaation'] ?? null) == 1) {
            $this->safely('slack', fn () => Utility::send_slack_msg($module, $workspace->id, $uArr));
        }

        if (($settings['telegram_invoicest_notificaation'] ?? null) == 1) {
            // send_telegram_msg() reads Auth::user(); this request comes from PayHere's
            // server, so act as the workspace owner for the template lookup only.
            $this->safely('telegram', fn () => $this->asWorkspaceOwner($workspace, fn () => Utility::send_telegram_msg($module, $uArr, $workspace->id)));
        }

        // Webhooks are stored with created_by = workspace id.
        $this->safely('webhook', function () use ($module, $workspace, $invoice) {
            $webhook = Utility::webhookSetting($module, $workspace->id);
            if ($webhook) {
                Utility::WebhookCall($webhook['url'], json_encode($invoice), $webhook['method']);
            }
        });
    }

    private function asWorkspaceOwner(Workspace $workspace, callable $callback): mixed
    {
        $guard = Auth::guard();
        if ($guard->check()) {
            return $callback();
        }

        $owner = $workspace->creater;
        if (! $owner) {
            return null;
        }

        $guard->setUser($owner);
        try {
            return $callback();
        } finally {
            $guard->forgetUser();
        }
    }

    private function safely(string $channel, callable $callback): void
    {
        try {
            ob_start();
            $callback();
        } catch (Throwable $e) {
            Log::warning('PayHere invoice notification failed.', ['channel' => $channel, 'exception' => get_class($e)]);
        } finally {
            ob_end_clean(); // send_slack_msg() echoes curl errors
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @throws PayHereCheckoutException
     */
    private function requireUsable(PayHereCredentials $credentials): PayHereCredentials
    {
        if (! $credentials->isUsable()) {
            throw new PayHereCheckoutException(__('PayHere is not configured.'));
        }

        return $credentials;
    }

    /**
     * @throws PayHereCheckoutException
     */
    private function supportedCurrency(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency)) ?: 'USD';

        if (! in_array($currency, PayHere::SUPPORTED_CURRENCIES, true)) {
            throw new PayHereCheckoutException(__('Availabe currencies: LKR, USD, GBP, EUR, AUD'));
        }

        return $currency;
    }

    /**
     * Temporarily point the package's config at one merchant account. The package
     * reads credentials from config(); this keeps admin and workspace accounts apart.
     */
    private function withPackageConfig(PayHereCredentials $credentials, array $extra, callable $callback): mixed
    {
        $original = config('payhere');

        config(array_merge($credentials->toPackageConfig(), $extra));

        try {
            return $callback();
        } finally {
            config(['payhere' => $original]);
        }
    }
}
