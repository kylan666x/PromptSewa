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
     * Common English stop words stripped before hitting Scout. The
     * `database` driver (LIKE on SQLite, FULLTEXT on MySQL) has no natural-
     * language understanding — "I want a blog" would match nothing under a
     * strict LIKE. Removing the filler leaves the content words ("blog").
     */
    private const STOP_WORDS = [
        'i', 'want', 'a', 'an', 'the', 'is', 'for', 'to', 'and', 'or',
        'in', 'on', 'of', 'with', 'me', 'my',
    ];

    /**
     * Search public prompts, or list the newest ones for a blank term.
     * Optional $type narrows to one prompt type (text|image|video).
     *
     * @return LengthAwarePaginator<int, Prompt>
     */
    public function search(string $term, int $perPage = 12, ?string $type = null): LengthAwarePaginator
    {
        $term = $this->normalizeQuery($term);

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
     * Normalize a raw search phrase for keyword engines:
     * trim → lowercase → drop stop words → collapse whitespace.
     * If everything is stripped (e.g. "the"), fall back to the trimmed
     * original so we never silently search for nothing.
     */
    public function normalizeQuery(string $term): string
    {
        $original = trim($term);

        $content = collect(preg_split('/\s+/u', mb_strtolower($original)) ?: [])
            ->reject(fn (string $word) => $word === '' || in_array($word, self::STOP_WORDS, true))
            ->implode(' ');

        return $content !== '' ? $content : $original;
    }

    /**
     * Search creator profiles by handle/name/email for the library page
     * sidebar section and the navbar typeahead. Only users with at least
     * one public prompt are surfaced.
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
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%"))
            ->whereHas('prompts', fn ($q) => $q->publicListing())
            ->withCount(['prompts' => fn ($q) => $q->publicListing()])
            ->orderByDesc('prompts_count')
            ->limit($limit)
            ->get();
    }
}
