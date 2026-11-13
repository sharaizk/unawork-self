<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayHere\InvoiceCheckoutRequest;
use App\Http\Requests\PayHere\PlanCheckoutRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PayHereCheckout;
use App\Models\Plan;
use App\Models\User;
use App\Models\Utility;
use App\Models\Workspace;
use App\Services\PayHere\PayHereCheckoutException;
use App\Services\PayHereService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PayHere checkout for plan purchases (admin credentials) and invoice payments
 * (workspace credentials). Payments are only ever confirmed by notify(); the
 * browser return/cancel endpoints just report the stored status.
 */
class PayHerePaymentController extends Controller
{
    public function __construct(private readonly PayHereService $payHere)
    {
    }

    // ---------------------------------------------------------------------
    // Plan purchase
    // ---------------------------------------------------------------------

    public function planPayWithPayhere(PlanCheckoutRequest $request)
    {
        $user = $request->user();
        $plan = ($planId = $request->planId()) ? Plan::find($planId) : null;

        if (! $plan) {
            return redirect()->route('plans.index')->with('error', __('Plan Not Found..'));
        }

        try {
            $checkout = $this->payHere->startPlanCheckout($user, $plan, $request->frequency(), $request->couponCode());

            if ($checkout->isCompleted()) {
                return redirect()->route('plans.index')->with('success', __('Plan Successfully Activated'));
            }

            return $this->payHere->checkoutResponse($checkout);
        } catch (PayHereCheckoutException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('PayHere plan checkout failed.', ['user_id' => $user->id, 'plan_id' => $plan->id, 'exception' => get_class($e), 'message' => $e->getMessage()]);

            return redirect()->back()->with('error', __('Something went wrong.'));
        }
    }

    // ---------------------------------------------------------------------
    // Invoice payment (public link, workspace member, or client guard)
    // ---------------------------------------------------------------------

    public function invoicePayWithPayhere(InvoiceCheckoutRequest $request, $slug, $invoice_id)
    {
        $actor = Auth::user(); // client on client.* routes (auth:client), web user or null otherwise
        $client = $actor instanceof Client ? $actor : null;
        $user = $actor instanceof User ? $actor : null;

        // Enforces workspace membership for users and client_workspaces for clients.
        $workspace = Utility::getWorkspaceBySlug($slug);

        $invoice = $workspace
            ? Invoice::where('id', $invoice_id)->where('workspace_id', $workspace->id)->first()
            : null;

        if (! $invoice) {
            return redirect()->back()->with('error', __('Invoice not found.'));
        }

        if ($client && (int) $invoice->client_id !== (int) $client->id) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            $checkout = $this->payHere->startInvoiceCheckout($invoice, $workspace, $user, $client, (float) $request->validated('amount'));

            return $this->payHere->checkoutResponse($checkout);
        } catch (PayHereCheckoutException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('PayHere invoice checkout failed.', ['invoice_id' => $invoice->id, 'exception' => get_class($e), 'message' => $e->getMessage()]);

            return redirect()->back()->with('error', __('Something went wrong.'));
        }
    }

    // ---------------------------------------------------------------------
    // PayHere server-to-server notification (notify_url)
    // ---------------------------------------------------------------------

    public function notify(Request $request)
    {
        // Only the fields needed for verification and bookkeeping; card data is ignored.
        $this->payHere->handleNotification($request->only([
            'merchant_id', 'order_id', 'payment_id', 'payhere_amount', 'payhere_currency',
            'status_code', 'md5sig', 'method', 'status_message',
        ]));

        // PayHere only needs a 200; verification failures are logged, not echoed.
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    // ---------------------------------------------------------------------
    // Browser return / cancel — display only, never confirm a payment
    // ---------------------------------------------------------------------

    public function paymentReturn(string $order)
    {
        $checkout = PayHereCheckout::where('order_id', $order)->first();
        if (! $checkout) {
            return redirect()->route('home')->with('error', __('Transaction Has Been Failed.'));
        }

        // A plan checkout's status is only reported back to the user who started it.
        if ($checkout->isPlan() && (int) Auth::guard('web')->id() !== (int) $checkout->user_id) {
            return redirect()->route('plans.index');
        }

        // PayHere usually notifies before redirecting the customer, but not always.
        for ($i = 0; $i < 4 && $checkout->status === PayHereCheckout::STATUS_PENDING; $i++) {
            usleep(750000);
            $checkout->refresh();
        }

        [$flash, $message] = match ($checkout->status) {
            PayHereCheckout::STATUS_COMPLETED => ['success', $checkout->isPlan() ? __('Plan activated Successfully!') : __('Payment added Successfully')],
            PayHereCheckout::STATUS_PENDING => ['error', __('We have not received payment confirmation from PayHere yet. Please check again in a few minutes.')],
            PayHereCheckout::STATUS_REVIEW => ['error', __('Your payment was received but needs manual review. Please contact support.')],
            default => ['error', __('Transaction Has Been Failed.')],
        };

        return $this->redirectFor($checkout)->with($flash, $message);
    }

    public function paymentCancel(string $order)
    {
        $checkout = PayHereCheckout::where('order_id', $order)->first();
        if (! $checkout) {
            return redirect()->route('home');
        }

        return $this->redirectFor($checkout)->with('error', __('Payment was cancelled.'));
    }

    /**
     * Previous return URLs (plan.payhere.status / invoice.payhere.status). Kept so the
     * route names still resolve; they only forward to the read-only return page.
     */
    public function legacyStatus(Request $request)
    {
        $order = (string) $request->query('order_id', '');

        if ($order !== '' && PayHereCheckout::where('order_id', $order)->exists()) {
            return redirect()->route('payhere.callback.return', ['order' => $order]);
        }

        return redirect()->route('home')->with('error', __('Transaction Has Been Failed.'));
    }

    private function redirectFor(PayHereCheckout $checkout): RedirectResponse
    {
        if ($checkout->isPlan()) {
            return redirect()->route('plans.index');
        }

        $workspace = Workspace::find($checkout->workspace_id);
        if (! $workspace) {
            return redirect()->route('home');
        }

        $client = Auth::guard('client')->user();
        if ($client && (int) $client->id === (int) $checkout->client_id) {
            return redirect()->route('client.invoices.show', [$workspace->slug, $checkout->invoice_id]);
        }

        $user = Auth::guard('web')->user();
        if ($user && $user->workspace()->where('workspaces.id', $workspace->id)->exists()) {
            return redirect()->route('invoices.show', [$workspace->slug, $checkout->invoice_id]);
        }

        return redirect()->route('pay.invoice', [$workspace->slug, Crypt::encrypt($checkout->invoice_id)]);
    }
}
