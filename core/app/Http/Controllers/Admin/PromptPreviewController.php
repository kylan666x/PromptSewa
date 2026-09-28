<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * S2 — moderation preview with a REAL route (no referer sniffing).
 *
 * Renders the public prompt detail view with the full body visible for
 * staff review, bypassing status and entitlement gates. Crucially it
 * grants NOTHING: no LicenseGrant row is ever created here — the paywall
 * invariant holds (there is a test that counts the table after previewing).
 */
class PromptPreviewController extends Controller
{
    public function show(Request $request, Prompt $prompt)
    {
        $viewer = $request->user();

        abort_unless($viewer !== null && $viewer->isModerator(), 403);

        $prompt->load(['category', 'creator', 'latestVersion']);

        Log::info('admin.prompt.preview', [
            'user_id' => $viewer->id,
            'prompt_id' => $prompt->id,
        ]);

        return view('prompts.show', [
            'prompt' => $prompt,
            'canEdit' => false,
            // Moderation preview: full body regardless of status/license.
            'canViewFullBody' => true,
            'previewMode' => true,
        ]);
    }
}
