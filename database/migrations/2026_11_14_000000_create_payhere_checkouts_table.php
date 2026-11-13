<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative pending-payment context for PayHere checkouts.
 *
 * orders / invoice_payments only hold completed payments and have no columns for
 * workspace, frequency, coupon, expected amount or processing state, so the
 * server-to-server notification is verified against this table instead of
 * trusting browser parameters.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payhere_checkouts')) {
            return;
        }

        Schema::create('payhere_checkouts', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 64)->unique();
            $table->string('type', 20); // plan | invoice
            $table->string('status', 20)->default('pending'); // pending | completed | failed | cancelled | review
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('frequency', 20)->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->decimal('amount', 25, 2);
            $table->string('currency', 3);
            $table->string('mode', 10)->default('sandbox'); // sandbox | live, at checkout time
            $table->string('merchant_id', 64)->nullable();
            $table->string('payhere_payment_id', 64)->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('status_message')->nullable();
            $table->string('failure_reason', 100)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('invoice_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payhere_checkouts');
    }
};
