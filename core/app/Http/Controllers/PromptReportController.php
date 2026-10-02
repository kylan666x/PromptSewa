<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Report this prompt" — public form + submission (guests welcome,
 * throttled; logged-in reporters are linked to their account).
 */
class PromptReportController extends Controller
{
    public function create(Request $request, Prompt $prompt)
    {
        abort_unless($prompt->isViewableBy($request->user()), 404);

        return view('prompts.report', [
            'prompt' => $prompt,
        ]);
    }

    public function store(Request $request, Prompt $prompt)
    {
        abort_unless($prompt->isViewableBy($request->user()), 404);

        // T4 (v1.7.3): bot challenge on reports (off by default).
        if (! app(\App\Services\BotChallengeService::class)->verify('report', $request->input('cf-turnstile-response') ?? $request->input('captcha_token'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'captcha' => 'Bot check failed — please retry.',
            ]);
        }

        // T5: the report contact email gets the disposable gate only when
        // the admin flips block_disposable_on_reports on (default off).
        $disposableRule = app(\App\Services\SettingsService::class)->isOn('block_disposable_on_reports')
            ? [new \App\Support\NotDisposableEmail()]
            : [];

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(PromptReport::REASONS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'reporter_email' => ['nullable', 'email', 'max:255', ...$disposableRule],
        ]);

        PromptReport::create([
            'prompt_id' => $prompt->id,
            'user_id' => $request->user()?->id,
            'reason' => $validated['reason'],
            'message' => $validated['message'],
            'reporter_email' => $validated['reporter_email'] ?? null,
        ]);

        return redirect()
            ->route('prompts.show', $prompt)
            ->with('report_submitted', true);
    }
}
