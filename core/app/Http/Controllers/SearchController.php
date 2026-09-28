<?php

namespace App\Http\Controllers;

use App\Services\PromptSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TASK 2 — AJAX predictive search for the navbar typeahead.
 *
 * Lightweight JSON endpoint: 3 prompt hits + 3 creator hits per keystroke
 * (debounced client-side). All queries flow through PromptSearchService —
 * controllers never write raw LIKE/MATCH (Sprint-1 abstraction gate).
 */
class SearchController extends Controller
{
    public function __construct(private readonly PromptSearchService $search) {}

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:'.PromptSearchService::MAX_TERM_LENGTH],
        ]);

        $term = trim($validated['q'] ?? '');

        if ($term === '') {
            return response()->json(['prompts' => [], 'creators' => []]);
        }

        $prompts = $this->search->search($term, 3)
            ->getCollection()
            ->take(3)
            ->map(fn ($prompt) => [
                'title' => $prompt->title,
                'type' => $prompt->type,
                'price' => $prompt->price_cents,
                'url' => route('prompts.show', $prompt),
            ])
            ->values();

        $creators = $this->search->searchCreators($term, 3)
            ->map(fn ($creator) => [
                'name' => $creator->name,
                'username' => $creator->username,
                'prompts_count' => $creator->prompts_count,
                'url' => route('creators.show', $creator),
            ])
            ->values();

        return response()->json([
            'prompts' => $prompts,
            'creators' => $creators,
        ]);
    }
}
