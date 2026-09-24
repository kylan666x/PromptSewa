<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Prompt;
use Illuminate\Http\Request;

/**
 * Admin packs CRUD: bundle published prompts into a single sellable item.
 */
class PackAdminController extends Controller
{
    public function index()
    {
        return view('admin.packs', [
            'packs' => Pack::query()
                ->withCount('publishedPrompts')
                ->orderBy('position')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.pack-form', [
            'pack' => new Pack(['currency' => 'NPR', 'is_active' => true]),
            'prompts' => $this->promptOptions(),
            'selectedIds' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $pack = Pack::query()->create($this->packAttributes($validated));
        $pack->prompts()->sync($validated['prompt_ids'] ?? []);

        return redirect()
            ->route('admin.packs.index')
            ->with('success', "Pack \"{$pack->name}\" created.");
    }

    public function edit(Pack $pack)
    {
        return view('admin.pack-form', [
            'pack' => $pack,
            'prompts' => $this->promptOptions(),
            'selectedIds' => $pack->prompts()->pluck('prompts.id'),
        ]);
    }

    public function update(Request $request, Pack $pack)
    {
        $validated = $this->validated($request);

        $pack->fill($this->packAttributes($validated, $pack->id))->save();
        $pack->prompts()->sync($validated['prompt_ids'] ?? []);

        return redirect()
            ->route('admin.packs.index')
            ->with('success', "Pack \"{$pack->name}\" updated.");
    }

    public function destroy(Pack $pack)
    {
        $name = $pack->name;
        $pack->delete(); // pack_prompt pivot cascades; orders keep their snapshot

        return back()->with('success', "Pack \"{$name}\" deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_npr' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'prompt_ids' => ['nullable', 'array'],
            'prompt_ids.*' => ['integer', 'exists:prompts,id'],
        ]);
    }

    /** @return array<string, mixed> */
    private function packAttributes(array $validated, ?int $ignoreId = null): array
    {
        return [
            'name' => trim((string) $validated['name']),
            'slug' => Pack::uniqueSlug((string) $validated['name'], $ignoreId),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'price_paisa' => max(0, (int) $validated['price_npr']) * 100,
            'currency' => 'NPR',
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'position' => (int) ($validated['position'] ?? 0),
        ];
    }

    private function promptOptions()
    {
        return Prompt::query()
            ->where('status', Prompt::STATUS_PUBLISHED)
            ->orderBy('title')
            ->get(['id', 'title', 'price_cents']);
    }
}
