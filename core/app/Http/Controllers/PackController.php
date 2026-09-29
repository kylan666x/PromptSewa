<?php

namespace App\Http\Controllers;

use App\Models\Pack;

/**
 * Public pack browsing: curated bundles of prompts at one price.
 */
class PackController extends Controller
{
    public function index()
    {
        $packs = Pack::query()
            ->active()
            ->withCount('publishedPrompts')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->filter(fn (Pack $pack) => $pack->published_prompts_count > 0)
            ->values();

        return view('packs.index', ['packs' => $packs]);
    }

    public function show(Pack $pack)
    {
        abort_unless($pack->is_active, 404);

        // Fix (v1.5.0): latest() emitted a bare `order by created_at` inside
        // the belongsToMany window function — ambiguous between prompts and
        // the pivot, so any pack with contents 500'd. Qualify the column.
        $pack->load(['publishedPrompts' => fn ($query) => $query->with(['category', 'creator', 'latestVersion'])
            ->orderByDesc('prompts.created_at')
            ->take(24)]);

        return view('packs.show', ['pack' => $pack]);
    }
}
