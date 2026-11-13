<?php

namespace App\Services\PayHere;

/**
 * PayHere merchant credentials for one account (super admin or a workspace).
 *
 * Built from the existing settings keys: is_payhere_enabled, payhere_mode
 * (sandbox|live), payhere_merchant_id, payhere_merchant_secret.
 */
final class PayHereCredentials
{
    public function __construct(
        public readonly bool $enabled,
        public readonly bool $sandbox,
        public readonly string $merchantId,
        #[\SensitiveParameter]
        private readonly string $merchantSecret,
    ) {
    }

    public static function fromSettings(array $settings): self
    {
        return new self(
            enabled: ($settings['is_payhere_enabled'] ?? 'off') === 'on',
            sandbox: ($settings['payhere_mode'] ?? 'sandbox') !== 'live',
            merchantId: trim((string) ($settings['payhere_merchant_id'] ?? '')),
            merchantSecret: trim((string) ($settings['payhere_merchant_secret'] ?? '')),
        );
    }

    public function isUsable(): bool
    {
        return $this->enabled && $this->merchantId !== '' && $this->merchantSecret !== '';
    }

    public function mode(): string
    {
        return $this->sandbox ? 'sandbox' : 'live';
    }

    public function baseUrl(): string
    {
        return $this->sandbox ? 'https://sandbox.payhere.lk' : 'https://www.payhere.lk';
    }

    /**
     * Expected md5sig of a PayHere payment notification.
     *
     * md5sig = UPPER(md5(merchant_id . order_id . amount(2dp) . currency . status_code . UPPER(md5(merchant_secret))))
     */
    public function notificationSignature(string $orderId, string $amount, string $currency, string $statusCode): string
    {
        return strtoupper(md5(
            $this->merchantId
            . $orderId
            . number_format((float) $amount, 2, '.', '')
            . $currency
            . $statusCode
            . strtoupper(md5($this->merchantSecret))
        ));
    }

    /**
     * Values the package's form builder reads from config('payhere.*').
     */
    public function toPackageConfig(): array
    {
        return [
            'payhere.sandbox' => $this->sandbox,
            'payhere.base_url' => $this->baseUrl(),
            'payhere.merchant_id' => $this->merchantId,
            'payhere.merchant_secret' => $this->merchantSecret,
        ];
    }

    public function __debugInfo(): array
    {
        return ['enabled' => $this->enabled, 'sandbox' => $this->sandbox, 'merchantId' => $this->merchantId];
    }
}
