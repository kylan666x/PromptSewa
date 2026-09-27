<?php

namespace App\Http\Controllers;

use App\Models\LicenseGrant;
use App\Models\Prompt;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Community ratings.
 *
 * Eligibility: paid prompts are rateable only by buyers holding an active
 * license grant; free prompts are rateable by any logged-in user. One
 * rating per user per prompt — re-rating updates in place (upsert).
 */
class RatingController extends Controller
{
    public function store(Request $request, Prompt $prompt)
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:'.Rating::MIN_SCORE, 'max:'.Rating::MAX_SCORE],
        ]);

        abort_unless($this->canRate($user, $prompt), 403, 'Only buyers can rate paid prompts.');

        DB::transaction(function () use ($user, $prompt, $validated) {
            Rating::query()->updateOrCreate(
                ['user_id' => $user->id, 'prompt_id' => $prompt->id],
                ['score' => $validated['score']]
            );
        });

        return back()->with('success', 'Thanks — your rating is in.');
    }

    /** Paid prompts: active-license holders only. Free prompts: any user. */
    private function canRate(object $user, Prompt $prompt): bool
    {
        if ($prompt->price_cents === 0) {
            return true;
        }

        return LicenseGrant::query()
            ->where('user_id', $user->id)
            ->where('prompt_id', $prompt->id)
            ->where('status', LicenseGrant::STATUS_ACTIVE)
            ->exists();
    }
}
