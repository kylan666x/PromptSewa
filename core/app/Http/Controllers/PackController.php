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

        $pack->load(['publishedPrompts' => fn ($query) => $query->with(['category', 'creator', 'latestVersion'])->latest()->take(24)]);

        return view('packs.show', ['pack' => $pack]);
    }
}
