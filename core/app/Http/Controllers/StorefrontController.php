<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Prompt;

/**
 * UI-001: the public storefront homepage — hero, featured prompts,
 * category tiles and library stats. No deployment/infrastructure copy.
 */
class StorefrontController extends Controller
{
    public function index()
    {
        $featured = Prompt::query()
            ->publicListing()
            ->with(['category', 'creator.activeFrame', 'creator', 'latestVersion'])
            ->latest()
            ->take(6)
            ->get();

        // Image-prompt gallery (PromptPlum-style): prefer prompts with a real
        // cover image so the wall renders visuals, fall back to placeholders.
        $imagePrompts = Prompt::query()
            ->publicListing()
            ->where('type', Prompt::TYPE_IMAGE)
            ->with(['category', 'creator.activeFrame', 'creator', 'latestVersion'])
            ->orderByDesc('cover_image_path')
            ->latest()
            ->take(8)
            ->get();

        $categories = Category::query()
            ->active()
            ->withCount(['prompts' => fn ($query) => $query->publicListing()])
            ->orderBy('position')
            ->get()
            ->filter(fn (Category $category) => $category->prompts_count > 0)
            ->values();

        $stats = [
            'prompts' => Prompt::query()->publicListing()->count(),
            'creators' => Prompt::query()->publicListing()->distinct()->count('user_id'),
            'free' => Prompt::query()->publicListing()->where('price_cents', 0)->count(),
        ];

        return view('storefront', [
            'featured' => $featured,
            'imagePrompts' => $imagePrompts,
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }
}
