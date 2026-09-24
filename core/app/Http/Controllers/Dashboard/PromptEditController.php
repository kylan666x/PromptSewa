<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromptFormRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Prompt;
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
            'tagsValue' => implode(', ', $latest?->tags ?? []),
            'tipsValue' => implode("\n", $latest?->tips ?? []),
        ]);
    }

    public function update(PromptFormRequest $request, Prompt $prompt)
    {
        Gate::authorize('update', $prompt);

        $validated = $request->fields();
        $user = $request->user();

        DB::transaction(function () use ($validated, $user, $request, $prompt): void {
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
            ])->save();

            // Append-only body history: next immutable version row.
            $next = ((int) $prompt->versions()->max('version_number')) + 1;

            $prompt->versions()->create([
                'version_number' => $next,
                'body' => $validated['body'],
                'tags' => $validated['tags'],
                'recommended_tools' => $validated['recommended_tools'],
                'audience' => $validated['audience'],
                'tips' => $validated['tips'],
                'changelog' => $request->filled('changelog')
                    ? trim((string) $request->input('changelog'))
                    : 'Updated prompt',
                'user_id' => $user->id,
            ]);

            // Keep the MKT-001 product price in sync with the listing price.
            $this->syncProduct($prompt, $validated['price_cents']);
        });

        return redirect()
            ->route('dashboard.prompts.edit', $prompt)
            ->with('success', 'Saved — version '.($prompt->versions()->max('version_number')).' committed to history.');
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
