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
            'manualEnabled' => $this->settings->isOn('manual_payment_enabled'),
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
            'manual_enabled' => ['nullable', 'boolean'],
            'manual_payment_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->settings->set('payments_enabled', $request->boolean('payments_enabled') ? '1' : '0');
        $this->settings->set('esewa_enabled', $request->boolean('esewa_enabled') ? '1' : '0');
        $this->settings->set('esewa_merchant_code', trim((string) ($validated['esewa_merchant_code'] ?? '')));
        $this->settings->set('esewa_base_url', trim((string) ($validated['esewa_base_url'] ?? '')) ?: 'https://rc.esewa.com.np');
        $this->settings->set('manual_enabled', $request->boolean('manual_enabled') ? '1' : '0');
        $this->settings->set('manual_payment_instructions', (string) ($validated['manual_payment_instructions'] ?? ''));

        // Empty means "keep the stored secret" — never wipe credentials by
        // submitting the form without retyping them.
        if (($validated['esewa_secret_key'] ?? '') !== '') {
            $this->settings->set('esewa_secret_key', trim((string) $validated['esewa_secret_key']));
        }

        return back()->with('success', 'Payment settings saved.');
    }
}
