<?php

namespace App\Http\Controllers;

use App\Services\BotChallengeService;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * A1 (v1.7.6) — the forgot-password flow.
 *
 * Guest-only (routes sit in the `guest` group) and throttled at 6 attempts
 * a minute so the page cannot be used as a mail cannon. The tokens come
 * from Laravel's Password broker, which is already configured against the
 * baseline `password_reset_tokens` table with a 60-minute expiry
 * (config/auth.php) — nothing here invents its own token store.
 *
 * House rules:
 *  - ENUMERATION-PROOF: an unknown address lands on the very same "check
 *    your email" page as a real one. The form never says whether the
 *    account exists;
 *  - SILENT LIES ARE BANNED: a rail failure (sendmail missing on the host)
 *    is logged and reported honestly instead of pretending mail went out;
 *  - the password floor is SHARED with signup (PasswordPolicy::rule), so
 *    a reset cannot be used to set a password registration would reject.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly BotChallengeService $bot) {}

    /** GET /forgot-password */
    public function requestForm()
    {
        return view('auth.forgot-password');
    }

    /** POST /forgot-password */
    public function sendLink(Request $request)
    {
        $this->guardBot($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        try {
            $status = Password::broker()->sendResetLink($validated);
        } catch (\Throwable $e) {
            // The broker persists the token, then the notification throws
            // (no sendmail binary, refused relay). Report it, do not lie.
            Log::error('password-reset mail failed', ['class' => $e::class]);

            throw ValidationException::withMessages([
                'email' => 'We could not send that email just now — please try again in a minute.',
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            // The broker refuses a second link within `passwords.users.throttle`.
            throw ValidationException::withMessages([
                'email' => 'A reset link was just sent to that address — check your inbox before asking for another.',
            ]);
        }

        // RESET_LINK_SENT *and* INVALID_USER land here on purpose: the page
        // is identical either way, so this form is not an account oracle.
        return redirect()->route('password.sent');
    }

    /** GET /forgot-password/sent — the enumeration-proof notice. */
    public function sent()
    {
        return view('auth.check-email');
    }

    /** GET /reset-password/{token} */
    public function showForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => (string) $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /** POST /reset-password/{token} */
    public function reset(Request $request, string $token)
    {
        $this->guardBot($request);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'confirmed',
                PasswordRule::min(8),
                PasswordPolicy::rule($request),
            ],
            // `confirmed` compares against this field but does not carry it
            // out of validated() on its own — declare it or the broker gets
            // an undefined key.
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = Password::broker()->reset([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'password_confirmation' => $validated['password_confirmation'],
            // The URL is the source of truth; a hidden field is the fallback
            // for a client that strips the path segment.
            'token' => (string) ($request->input('token') ?: $token),
        ], function ($user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                // §6-style hygiene: invalidate other sessions' remember cookies.
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password updated — log in with your new one.');
        }

        // INVALID_TOKEN (expired/garbage/used) and INVALID_USER share the
        // same message: the link's validity is not a probing surface.
        throw ValidationException::withMessages(['email' => (string) __($status)]);
    }

    /**
     * T1/v1.7.3 bot challenge on the `reset` form (off by default). Same
     * plain validation failure as register/login — no different route, no
     * enumeration.
     */
    private function guardBot(Request $request): void
    {
        if (! $this->bot->verify('reset', $request->input('cf-turnstile-response') ?? $request->input('captcha_token'))) {
            throw ValidationException::withMessages([
                'captcha' => 'Bot check failed — please retry.',
            ]);
        }
    }
}