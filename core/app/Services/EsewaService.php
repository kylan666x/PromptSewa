<?php

namespace App\Services;

use App\Models\Order;
use RuntimeException;

/**
 * eSewa v2 payment integration.
 *
 * Signature: HMAC-SHA256 over "total_amount=…,transaction_uuid=…,product_code=…"
 * base64url-encoded, signed with the merchant secret. The verification
 * signature over the gateway's returned payload uses the same secret.
 *
 * All amounts are passed as decimal strings with exactly 2 places (eSewa's
 * wire format) — derived from integer paisa, never float math.
 */
class EsewaService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /** Build the auto-submit form fields for the eSewa hosted checkout. */
    public function buildPaymentForm(Order $order): array
    {
        $amount = $this->formatAmount($order->total_paisa);

        $message = 'total_amount='.$amount
            .',transaction_uuid='.$order->id
            .',product_code='.$this->merchantCode();

        $fields = [
            'amount' => $amount,
            'tax_amount' => '0',
            'total_amount' => $amount,
            'transaction_uuid' => (string) $order->id,
            'product_code' => $this->merchantCode(),
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            'success_url' => route('checkout.esewa.verify'),
            'failure_url' => route('checkout.show', ['order' => $order->id]),
            'signed_field_names' => 'total_amount,transaction_uuid,product_code',
            'signature' => $this->sign($message),
        ];

        return [
            'action' => rtrim((string) $this->settings->get('esewa_base_url', 'https://rc.esewa.com.np'), '/').'/epay/main/v2/form',
            'fields' => $fields,
        ];
    }

    /**
     * Verify the gateway callback: recompute the HMAC over the signed
     * fields and compare (constant-time). Returns the gateway reference.
     *
     * @param  array<string, string>  $payload
     */
    public function verifyCallback(array $payload): string
    {
        $signedFields = (string) ($payload['signed_field_names'] ?? '');
        $signature = (string) ($payload['signature'] ?? '');

        $fieldNames = explode(',', $signedFields);
        $parts = [];
        foreach ($fieldNames as $field) {
            $parts[] = $field.'='.($payload[$field] ?? '');
        }

        $expected = $this->sign(implode(',', $parts));

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new RuntimeException('eSewa signature verification failed.');
        }

        return (string) ($payload['transaction_code'] ?? '');
    }

    private function sign(string $message): string
    {
        $secret = (string) $this->settings->get('esewa_secret_key', '');

        if ($secret === '') {
            throw new RuntimeException('eSewa secret key is not configured.');
        }

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $message, $secret, true)), '+/', '-_'), '=');
    }

    private function merchantCode(): string
    {
        $code = (string) $this->settings->get('esewa_merchant_code', '');

        if ($code === '') {
            throw new RuntimeException('eSewa merchant code is not configured.');
        }

        return $code;
    }

    /** Integer paisa → "199.00" style wire string (no float math). */
    private function formatAmount(int $paisa): string
    {
        return number_format($paisa / 100, 2, '.', '');
    }
}
