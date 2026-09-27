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
     * Optional $type narrows to one prompt type (text|image|video).
     *
     * @return LengthAwarePaginator<int, Prompt>
     */
    public function search(string $term, int $perPage = 12, ?string $type = null): LengthAwarePaginator
    {
        $term = trim($term);

        if ($term === '') {
            return Prompt::query()
                ->publicListing()
                ->when($type !== null, fn ($query) => $query->where('type', $type))
                ->with(['category', 'creator', 'latestVersion'])
                ->latest()
                ->paginate($perPage);
        }

        return Prompt::search($term)
            ->query(fn ($query) => $query
                ->publicListing()
                ->when($type !== null, fn ($q) => $q->where('type', $type))
                ->with(['category', 'creator', 'latestVersion']))
            ->paginate($perPage);
    }

    /**
     * Search creator profiles by name for the library page sidebar section.
     * Only users with at least one public prompt are surfaced.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    public function searchCreators(string $term, int $limit = 6): \Illuminate\Support\Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        return \App\Models\User::query()
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"))
            ->whereHas('prompts', fn ($q) => $q->publicListing())
            ->withCount(['prompts' => fn ($q) => $q->publicListing()])
            ->orderByDesc('prompts_count')
            ->limit($limit)
            ->get();
    }
}
