<?php

namespace App\Services;

use App\Models\Prompt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * UI-001: the ONLY search entry point for public catalog surfaces.
 *
 * Keeps the Sprint-1 abstraction gate (AGENTS.md #6): controllers never
 * touch MATCH()/LIKE — everything flows through Scout (`Prompt::search()`),
 * with a callback constraint that enforces the public-visibility contract.
 * Swapping SCOUT_DRIVER (database → meilisearch) needs zero changes here.
 */
class PromptSearchService
{
    public const MAX_TERM_LENGTH = 80;

    /**
     * Search public prompts, or list the newest ones for a blank term.
     *
     * @return LengthAwarePaginator<int, Prompt>
     */
    public function search(string $term, int $perPage = 12): LengthAwarePaginator
    {
        $term = trim($term);

        if ($term === '') {
            return Prompt::query()
                ->publicListing()
                ->with(['category', 'creator', 'latestVersion'])
                ->latest()
                ->paginate($perPage);
        }

        return Prompt::search($term)
            ->query(fn ($query) => $query
                ->publicListing()
                ->with(['category', 'creator', 'latestVersion']))
            ->paginate($perPage);
    }
}
