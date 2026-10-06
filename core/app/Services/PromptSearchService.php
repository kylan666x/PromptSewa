<?php

namespace App\Services;

use App\Models\Pack;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
                ->with(['category', 'creator.activeFrame', 'creator', 'latestVersion'])
                ->latest()
                ->paginate($perPage);
        }

        return Prompt::search($term)
            ->query(fn ($query) => $query
                ->publicListing()
                ->when($type !== null, fn ($q) => $q->where('type', $type))
                ->with(['category', 'creator.activeFrame', 'creator', 'latestVersion']))
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
     * S1b (v1.9.0) — search sellable PACKS by name/slug/tagline/description
     * for the navbar typeahead.
     *
     * Only active packs that actually contain published prompts surface: an
     * empty bundle is not a product. Ordering matches the /packs index
     * (admin position, then name) so the typeahead and the page agree.
     *
     * @return Collection<int, Pack>
     */
    public function searchPacks(string $term, int $limit = 3): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        return Pack::query()
            ->active()
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('tagline', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"))
            ->whereHas('publishedPrompts')
            ->withCount(['publishedPrompts'])
            ->orderBy('position')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Search creator profiles by handle/name/email for the library page
     * sidebar section and the navbar typeahead. Only users with at least
     * one public prompt are surfaced. Eager-loads activeFrame (W1: the
     * typeahead rows render the creator's equipped frame).
     *
     * @return Collection<int, User>
     */
    public function searchCreators(string $term, int $limit = 6): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        return User::query()
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%"))
            ->whereHas('prompts', fn ($q) => $q->publicListing())
            ->withCount(['prompts' => fn ($q) => $q->publicListing()])->with('activeFrame')
            ->orderByDesc('prompts_count')
            ->limit($limit)
            ->get();
    }
}
