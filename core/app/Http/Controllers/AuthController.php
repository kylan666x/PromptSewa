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
                // A1 (v1.7.6): the floor lives in PasswordPolicy so the
                // forgot-password reset enforces exactly what signup does.
                \App\Support\PasswordPolicy::rule($request, (string) $request->input('name')),
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
