<?php

namespace App\Support;

use App\Models\Bookmark;
use App\Models\Prompt;

/**
 * H4 (v1.7.4) — the viewer's bookmarked prompt ids, resolved ONCE per request.
 *
 * Root cause of "saved-heart state lost on refresh": only the library grid
 * passed a pre-flipped `$saved` into x-prompt-card, so every other surface
 * (home fresh grid, creator profile grid, …) rendered `saved = false`
 * regardless of the bookmark row. The optimistic Alpine flip hid the bug
 * until the next refresh flipped the heart back — the classic
 * "passes tests, fails browser" shape.
 *
 * This set is the parity fix: any view or component asks
 * `BookmarkedIds::contains($prompt)` and gets the truth for the current
 * viewer (always empty for guests). It is memoized on the CONTAINER — not
 * in a static property — so it is rebuilt for every request and every test
 * case, and never leaks between users or between RefreshDatabase tests.
 *
 * The old `Bookmark::isSaved()` per-row query stays available for callers
 * that need a single row, but any loop over prompts must use this set
 * (one query per request instead of one per card).
 */
class BookmarkedIds
{
    /**
     * @return array<int, true>  set of bookmarked prompt ids (isset-friendly)
     */
    public static function set(): array
    {
        // The binding is registered in AppServiceProvider (same pattern as
        // 'disposable.domains') and resolved lazily once per request.
        return app('bookmarked.ids');
    }

    /** @return array<int, true> */
    public static function resolve(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        return Bookmark::query()
            ->where('user_id', $user->id)
            ->pluck('prompt_id')
            ->mapWithKeys(fn ($id) => [$id => true])
            ->all();
    }

    public static function contains(int|Prompt $prompt): bool
    {
        $id = $prompt instanceof Prompt ? $prompt->id : $prompt;

        return isset(self::set()[$id]);
    }
}