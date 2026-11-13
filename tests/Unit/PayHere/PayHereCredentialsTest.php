<?php

namespace Tests\Unit\PayHere;

use App\Services\PayHere\PayHereCredentials;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests (no application boot, no database).
 */
class PayHereCredentialsTest extends TestCase
{
    private function credentials(array $overrides = []): PayHereCredentials
    {
        return PayHereCredentials::fromSettings(array_merge([
            'is_payhere_enabled' => 'on',
            'payhere_mode' => 'sandbox',
            'payhere_merchant_id' => '1211149',
            'payhere_merchant_secret' => 'not-a-real-secret',
        ], $overrides));
    }

    private function payHereSignature(string $orderId, string $amount, string $currency, string $status, string $merchantId = '1211149', string $secret = 'not-a-real-secret'): string
    {
        return strtoupper(md5($merchantId . $orderId . $amount . $currency . $status . strtoupper(md5($secret))));
    }

    public function test_it_reads_existing_settings_keys(): void
    {
        $sandbox = $this->credentials();
        $this->assertTrue($sandbox->isUsable());
        $this->assertTrue($sandbox->sandbox);
        $this->assertSame('https://sandbox.payhere.lk', $sandbox->baseUrl());

        $live = $this->credentials(['payhere_mode' => 'live']);
        $this->assertFalse($live->sandbox);
        $this->assertSame('https://www.payhere.lk', $live->baseUrl());
        $this->assertSame('live', $live->mode());
    }

    public function test_it_is_unusable_when_disabled_or_incomplete(): void
    {
        $this->assertFalse($this->credentials(['is_payhere_enabled' => 'off'])->isUsable());
        $this->assertFalse($this->credentials(['payhere_merchant_secret' => ''])->isUsable());
        $this->assertFalse(PayHereCredentials::fromSettings([])->isUsable());
    }

    public function test_notification_signature_matches_payhere_formula(): void
    {
        $expected = $this->payHereSignature('UNW01TEST', '1500.00', 'LKR', '2');

        $this->assertSame($expected, $this->credentials()->notificationSignature('UNW01TEST', '1500.00', 'LKR', '2'));
        // PayHere may send "1500" or "1500.0"; the signature uses 2 decimals.
        $this->assertSame($expected, $this->credentials()->notificationSignature('UNW01TEST', '1500', 'LKR', '2'));
    }

    public function test_signature_changes_with_any_tampered_field(): void
    {
        $valid = $this->payHereSignature('UNW01TEST', '1500.00', 'LKR', '2');
        $creds = $this->credentials();

        $this->assertNotSame($valid, $creds->notificationSignature('UNW01TEST', '15.00', 'LKR', '2'));
        $this->assertNotSame($valid, $creds->notificationSignature('UNW01TEST', '1500.00', 'USD', '2'));
        $this->assertNotSame($valid, $creds->notificationSignature('UNW01OTHER', '1500.00', 'LKR', '2'));
        $this->assertNotSame($valid, $creds->notificationSignature('UNW01TEST', '1500.00', 'LKR', '0'));
    }

    public function test_a_different_workspace_secret_does_not_validate(): void
    {
        $signedByWorkspaceA = $this->payHereSignature('UNW01TEST', '99.00', 'USD', '2', '1211149', 'secret-a');
        $workspaceB = $this->credentials(['payhere_merchant_secret' => 'secret-b']);

        $this->assertNotSame($signedByWorkspaceA, $workspaceB->notificationSignature('UNW01TEST', '99.00', 'USD', '2'));
    }

    public function test_secret_is_not_exposed_when_dumped(): void
    {
        $this->assertStringNotContainsString('not-a-real-secret', print_r($this->credentials(), true));
        $this->assertStringNotContainsString('not-a-real-secret', var_export($this->credentials()->__debugInfo(), true));
    }
}
