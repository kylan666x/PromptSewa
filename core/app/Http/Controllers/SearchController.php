<?php

namespace App\Http\Controllers;

use App\Services\PromptSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        // T9: JSON endpoints must never be indexed — header applied to every
        // response below.
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:'.PromptSearchService::MAX_TERM_LENGTH],
        ]);

        $term = trim($validated['q'] ?? '');

        if ($term === '') {
            return response()->json(['prompts' => [], 'creators' => []])->header('X-Robots-Tag', 'noindex');
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
                // C1: real avatar in the typeahead — null → client renders
                // the deterministic initials fallback.
                'avatar_url' => $creator->avatar_path
                    ? Storage::disk('public')->url($creator->avatar_path)
                    : null,
                'initial' => mb_substr($creator->name, 0, 1),
                // T2 (v1.5.0): badge variant for the typeahead — official
                // (blue house-account mark) wins over verified (saffron seal).
                'badge' => $creator->is_official ? 'official' : ($creator->is_verified ? 'verified' : 'none'),
                // W1 (v1.7.3): frame parity — the typeahead row carries the
                // equipped frame URL (null when none) so the client renders
                // the same ring as every other avatar surface.
                'frame_url' => $creator->activeFrame?->image_path
                    ? Storage::disk('public')->url($creator->activeFrame->image_path)
                    : null,
            ])
            ->values();

        return response()->json([
            'prompts' => $prompts,
            'creators' => $creators,
        ])->header('X-Robots-Tag', 'noindex');
    }
}
