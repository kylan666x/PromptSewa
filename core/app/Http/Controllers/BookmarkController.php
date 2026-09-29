<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Prompt;
use Illuminate\Http\Request;

/**
 * T13 (v1.5.0): bookmark toggle. Owner-of-session only (the route is
 * auth-gated; no user_id input is trusted from the client), throttled
 * 30/min. Idempotent: POST saves if absent, removes if present, and
 * returns the resulting state for Alpine's optimistic flip.
 */
class BookmarkController extends Controller
{
    public function toggle(Request $request, Prompt $prompt)
    {
        $user = $request->user();

        abort_unless($prompt->isViewableBy($user), 404);

        $existing = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('prompt_id', $prompt->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $saved = false;
        } else {
            Bookmark::query()->create([
                'user_id' => $user->id,
                'prompt_id' => $prompt->id,
            ]);
            $saved = true;
        }

        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved]);
        }

        return back()->with($saved ? 'success' : 'info', $saved
            ? 'Saved to your library.'
            : 'Removed from your saved prompts.');
    }
}
