<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromptFormRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\PromptVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Creator edit flow — versioned like git, not overwritten.
 *
 * Saving an edit never mutates an existing prompt_versions row (append-only
 * history, PRD §3.3): every save commits the next version_number with a
 * changelog. Listing metadata (title/description/price/visibility/category)
 * updates in place; the body/tools/tags/audience/tips payload lives in the
 * new version row. Public detail pages always render latestVersion.
 */
class PromptEditController extends Controller
{
    public function edit(Prompt $prompt)
    {
        Gate::authorize('update', $prompt);

        $latest = $prompt->latestVersion;

        return view('dashboard.prompts.edit', [
            'prompt' => $prompt->load('category'),
            'latest' => $latest,
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('name')
                ->get(['id', 'name', 'type_scope']),
            'typeContexts' => PromptFormController::TYPE_CONTEXTS,
            'tools' => \App\Models\ToolLogo::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get(['name', 'modality', 'is_active']),
            'tagsValue' => implode(', ', $latest?->tags ?? []),
            'tipsValue' => implode("\n", $latest?->tips ?? []),
        ]);
    }

    public function update(PromptFormRequest $request, Prompt $prompt)
    {
        Gate::authorize('update', $prompt);

        $validated = $request->fields();
        $user = $request->user();

        // Cover art replacement (image prompts only, GD-compressed).
        $uploader = app(\App\Services\ImageUploadService::class);
        if ($request->boolean('remove_cover') && $prompt->cover_image_path) {
            $uploader->delete($prompt->cover_image_path);
            $prompt->cover_image_path = null;
        }
        if ($request->hasFile('cover_image')) {
            try {
                $newPath = $uploader->store($request->file('cover_image'), 'cover');
            } catch (\RuntimeException $e) {
                return back()->withInput()->withErrors(['cover_image' => $e->getMessage()]);
            }
            $uploader->delete($prompt->cover_image_path);
            $prompt->cover_image_path = $newPath;
        }

        DB::transaction(function () use ($validated, $user, $request, $prompt): void {
            // T7 (v1.5.0): a published listing stays published through an
            // edit — the edit commits a new version, it never silently
            // unlists (or re-moderates) live content.
            $wasPublished = $prompt->status === Prompt::STATUS_PUBLISHED;

            // Listing metadata updates in place.
            $prompt->fill([
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'type' => $validated['type'],
                'visibility' => $validated['visibility'],
                'search_text' => $request->searchText(),
                'license_tier' => $validated['license_tier'],
                'price_cents' => $validated['price_cents'],
                'cover_image_path' => $prompt->cover_image_path,
            ])->save();

            // Append-only body history: next immutable version row, now
            // carrying the full body/variables/tools snapshot (T7).
            $next = ((int) $prompt->versions()->max('version_number')) + 1;

            $version = $prompt->versions()->create([
                'version_number' => $next,
                'body' => $validated['body'],
                'variables' => $this->extractVariables((string) $validated['body']),
                'tools' => $validated['recommended_tools'],
                'tags' => $validated['tags'],
                'recommended_tools' => $validated['recommended_tools'],
                'audience' => $validated['audience'],
                'tips' => $validated['tips'],
                'changelog' => $request->filled('changelog')
                    ? trim((string) $request->input('changelog'))
                    : 'Updated prompt',
                'user_id' => $user->id,
                'status' => $wasPublished ? PromptVersion::STATUS_PUBLISHED : PromptVersion::STATUS_PENDING,
            ]);

            // T7: a live listing with an OPEN abuse report goes back into
            // the moderation queue after an edit (the report may be about
            // the content that just changed).
            if ($wasPublished) {
                \App\Models\PromptReport::query()
                    ->where('prompt_id', $prompt->id)
                    ->where('status', \App\Models\PromptReport::STATUS_OPEN)
                    ->update(['status' => 'pending']);
            }

            // Keep the MKT-001 product price in sync with the listing price.
            $this->syncProduct($prompt, $validated['price_cents']);
        });

        return redirect()
            ->route('dashboard.prompts.edit', $prompt)
            ->with('success', 'Saved — version '.($prompt->versions()->max('version_number')).' committed to history.');
    }

    /**
     * T7: restore an old snapshot as the newest version (append-only —
     * nothing in history is mutated or deleted). Owner or moderator.
     */
    public function restore(Request $request, Prompt $prompt, PromptVersion $version)
    {
        Gate::authorize('update', $prompt);

        abort_unless($version->prompt_id === $prompt->id, 404);
        abort_unless($version->hasSnapshot(), 422, 'That version has no snapshot to restore from.');

        $next = ((int) $prompt->versions()->max('version_number')) + 1;

        $prompt->versions()->create([
            'version_number' => $next,
            'body' => $version->body,
            'variables' => $version->variables,
            'tools' => $version->tools,
            'tags' => $version->tags,
            'recommended_tools' => $version->recommended_tools,
            'audience' => $version->audience,
            'tips' => $version->tips,
            'changelog' => "Restored from {$version->label()}",
            'user_id' => $request->user()->id,
            'status' => $prompt->status === Prompt::STATUS_PUBLISHED
                ? PromptVersion::STATUS_PUBLISHED
                : PromptVersion::STATUS_PENDING,
        ]);

        return redirect()
            ->route('prompts.versions', $prompt)
            ->with('success', "Restored {$version->label()} as the new version {$next}.");
    }

    /** @return list<string> */
    private function extractVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_ -]+?)\s*\}\}/', $body, $matches);

        return collect($matches[1])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Create or reprice the sellable product; archive it for free listings. */
    private function syncProduct(Prompt $prompt, int $priceCents): void
    {
        /** @var Product|null $product */
        $product = $prompt->product()->first();

        if ($priceCents > 0) {
            if ($product === null) {
                $prompt->product()->create([
                    'price_paisa' => $priceCents,
                    'currency' => 'NPR',
                    'status' => Product::STATUS_ACTIVE,
                ]);

                return;
            }

            $product->fill([
                'price_paisa' => $priceCents,
                'status' => Product::STATUS_ACTIVE,
            ])->save();

            return;
        }

        // Became free: no sellable product should remain active.
        if ($product !== null && $product->isActive()) {
            $product->fill(['status' => Product::STATUS_ARCHIVED])->save();
        }
    }
}
