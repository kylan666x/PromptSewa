<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * UI-001: minimal session auth so the navbar's guest/auth links resolve.
 * Full auth hardening (verification, rate-limited login, etc.) ships later.
 */
class AuthController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        // T4 (v1.7.3): bot challenge on register (on by default). Failure is
        // a plain validation error — no different route, no enumeration.
        if (! app(\App\Services\BotChallengeService::class)->verify('register', $request->input('cf-turnstile-response') ?? $request->input('captcha_token'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'captcha' => 'Bot check failed — please retry.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            // S4: the handle is the identity — required, normalized, unique.
            'username' => [
                'required',
                'string',
                'min:4',
                'max:30',
                'alpha_dash',
                'unique:users,username',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
                // T5 (v1.7.3): disposable inboxes are refused at signup.
                new \App\Support\NotDisposableEmail(),
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8),
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if (\App\Support\PasswordPolicy::isCommon((string) $value)) {
                        $fail('That password is too common — choose something less guessable.');
                    }

                    $haystack = mb_strtolower((string) $value);

                    // Name containment: check each token ("sita", "sharma")
                    // so "SitaSharma2026!" is caught even though the raw
                    // display name contains a space. Read from $request —
                    // $validated does not exist inside the rule yet.
                    foreach (preg_split('/\s+/u', mb_strtolower(trim((string) $request->input('name')))) ?: [] as $token) {
                        if (mb_strlen($token) >= 3 && str_contains($haystack, $token)) {
                            $fail('Your password must not contain your name.');

                            return;
                        }
                    }

                    $emailLocal = mb_strtolower(\Illuminate\Support\Str::before((string) $request->input('email', ''), '@'));

                    if (mb_strlen($emailLocal) >= 4 && str_contains($haystack, $emailLocal)) {
                        $fail('Your password must not contain your email address.');
                    }
                },
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => mb_strtolower($validated['username']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_MEMBER,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // T4 (v1.7.3): bot challenge on login (off by default).
        if (! app(\App\Services\BotChallengeService::class)->verify('login', $request->input('cf-turnstile-response') ?? $request->input('captcha_token'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'captcha' => 'Bot check failed — please retry.',
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->route('home');
        }

        return back()
            ->withErrors(['email' => 'These credentials do not match our records.'])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
