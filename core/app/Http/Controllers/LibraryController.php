<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Prompt;
use App\Services\PromptSearchService;
use Illuminate\Http\Request;

/**
 * UI-001: the prompt library grid and category pages.
 *
 * - Search goes through the Scout-backed PromptSearchService only
 *   (never raw LIKE/MATCH — Sprint 1 gate).
 * - Search terms are trimmed and length-limited.
 * - Category pages resolve via slug route binding; inactive ones 404.
 */
class LibraryController extends Controller
{
    public function __construct(private readonly PromptSearchService $search) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:'.PromptSearchService::MAX_TERM_LENGTH],
        ]);

        $term = trim($validated['q'] ?? '');

        return view('library', [
            'prompts' => $this->search->search($term),
            'categories' => $this->sidebarCategories(),
            'category' => null,
            'q' => $term,
        ]);
    }

    public function category(Category $category)
    {
        abort_unless($category->is_active, 404);

        return view('library', [
            'prompts' => $category->prompts()
                ->publicListing()
                ->with(['category', 'creator', 'latestVersion'])
                ->latest()
                ->paginate(12),
            'categories' => $this->sidebarCategories(),
            'category' => $category,
            'q' => '',
        ]);
    }

    /**
     * Active categories that actually hold public prompts, ordered
     * for the library sidebar.
     */
    private function sidebarCategories()
    {
        return Category::query()
            ->active()
            ->withCount(['prompts' => fn ($query) => $query->publicListing()])
            ->orderBy('position')
            ->get()
            ->filter(fn (Category $category) => $category->prompts_count > 0)
            ->values();
    }
}
