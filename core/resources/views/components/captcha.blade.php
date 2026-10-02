@props(['form'])

@php
    /**
     * T3 (v1.7.3) — the CAPTCHA widget.
     *
     * Renders NOTHING when the provider is null or the form's enable is
     * off (zero markup, zero script tags). Turnstile: widget div + script
     * once per page. reCAPTCHA v3: hidden token input + Alpine submit
     * hook that runs grecaptcha.execute with the form name as action.
     *
     * Paper-world auth pages: the widget sits above the submit button with
     * visible focus states (WCAG) — see the auth views.
     *
     * @var string $form
     */
    $bot = \App\Services\BotChallengeService::class;
    $service = app($bot);
    $active = $service->enabledFor($form);
    $provider = $service->provider();
@endphp

@if ($active && $provider === 'turnstile')
    <div class="cf-turnstile my-4"
         data-sitekey="{{ $service->siteKey() }}"
         data-theme="light"
         role="group"
         aria-label="Bot check"></div>
    <script src="{{ config('captcha.scripts.turnstile') }}" async defer></script>
@elseif ($active && $provider === 'recaptcha_v3')
    <span x-data="captchaToken({ action: @js($form) })" @submit="tokenize($event)">
        <input type="hidden" name="captcha_token" :value="token">
    </span>
    <script src="{{ config('captcha.scripts.recaptcha_v3') }}{{ $service->siteKey() }}"></script>
@endif
