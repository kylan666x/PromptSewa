<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Request;

/**
 * Admin payment-method configuration.
 *
 * Secrets (eSewa merchant secret) are encrypted at rest via
 * SettingsService and never echoed back into the form — the input shows
 * an empty "replace secret" field with a "saved" marker instead.
 */
class PaymentMethodAdminController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function edit()
    {
        return view('admin.payments', [
            'esewaEnabled' => $this->settings->isOn('esewa_enabled'),
            'esewaMerchantCode' => (string) $this->settings->get('esewa_merchant_code', ''),
            'esewaBaseUrl' => (string) $this->settings->get('esewa_base_url', 'https://rc.esewa.com.np'),
            'esewaSecretSaved' => (string) $this->settings->get('esewa_secret_key', '') !== '',
            // M3 (v1.6.0): sandbox rail + last-webhook info line.
            'esewaSandbox' => $this->settings->isOn('esewa_sandbox'),
            'esewaSandboxMerchantCode' => (string) $this->settings->get('esewa_sandbox_merchant_code', 'EPAYTEST'),
            'esewaSandboxSecretSaved' => (string) $this->settings->get('esewa_sandbox_secret_key', '') !== '',
            'esewaLastWebhook' => (string) $this->settings->get('esewa_last_webhook', ''),
            'manualEnabled' => $this->settings->isOn('manual_payment_enabled'),
            // keep legacy alias visible for BC if a key exists
            'manualInstructions' => (string) $this->settings->get('manual_payment_instructions', ''),
            'paymentsEnabled' => $this->settings->isOn('payments_enabled'),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can change payment settings.');

        $validated = $request->validate([
            'payments_enabled' => ['nullable', 'boolean'],
            'esewa_enabled' => ['nullable', 'boolean'],
            'esewa_merchant_code' => ['nullable', 'string', 'max:60'],
            'esewa_base_url' => ['nullable', 'url', 'max:190'],
            'esewa_secret_key' => ['nullable', 'string', 'max:500'],
            'esewa_sandbox' => ['nullable', 'boolean'],
            'esewa_sandbox_merchant_code' => ['nullable', 'string', 'max:60'],
            'esewa_sandbox_secret_key' => ['nullable', 'string', 'max:500'],
            'manual_enabled' => ['nullable', 'boolean'],
            'manual_payment_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->settings->set('payments_enabled', $request->boolean('payments_enabled') ? '1' : '0');
        $this->settings->set('esewa_enabled', $request->boolean('esewa_enabled') ? '1' : '0');
        $this->settings->set('esewa_merchant_code', trim((string) ($validated['esewa_merchant_code'] ?? '')));
        $this->settings->set('esewa_base_url', trim((string) ($validated['esewa_base_url'] ?? '')) ?: 'https://rc.esewa.com.np');
        // C2 fix (v1.4.4): the form field is manual_enabled but the runtime
        // key (SettingsService::DEFAULTS + CheckoutController) is
        // manual_payment_enabled — the toggle previously saved to a key
        // nothing read, so the manual panel never activated and the
        // checkbox re-rendered unchecked. Persist under BOTH keys' canonical
        // name: the form keeps its field name for BC.
        $this->settings->set('manual_payment_enabled', $request->boolean('manual_enabled') ? '1' : '0');
        $this->settings->set('manual_payment_instructions', (string) ($validated['manual_payment_instructions'] ?? ''));

        // Empty means "keep the stored secret" — never wipe credentials by
        // submitting the form without retyping them.
        if (($validated['esewa_secret_key'] ?? '') !== '') {
            $this->settings->set('esewa_secret_key', trim((string) $validated['esewa_secret_key']));
        }

        // M3 (v1.6.0): sandbox rail settings (same write-only secret rule).
        $this->settings->set('esewa_sandbox', $request->boolean('esewa_sandbox') ? '1' : '0');
        $this->settings->set('esewa_sandbox_merchant_code', trim((string) ($validated['esewa_sandbox_merchant_code'] ?? '')) ?: 'EPAYTEST');

        if (($validated['esewa_sandbox_secret_key'] ?? '') !== '') {
            $this->settings->set('esewa_sandbox_secret_key', trim((string) $validated['esewa_sandbox_secret_key']));
        }

        return back()->with('success', 'Payment settings saved.');
    }
}
