<?php

namespace App\Http\Requests;

use App\Models\Prompt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared validation + normalization for the prompt create/edit forms.
 *
 * Tags arrive comma-separated and tips line-by-line (friendlier than
 * array inputs); fields() converts them to the JSON columns the models
 * persist. Money stays integer NPR → paisa — never floats.
 */
class PromptFormRequest extends FormRequest
{
    public const MAX_TAGS = 5;

    public const MAX_TIPS = 5;

    /**
     * H1 (v1.7.3 hotfix): prompt-body ceiling. 4,000 chars was the v1.0
     * floor; serious system/agent prompts need more. 50,000 sits comfortably
     * below Claude's ~40k-token (~200k char) message budget while staying a
     * sane guardrail (≈12k words / ~100KB). The DB column is LONGTEXT after
     * migration 2026_09_30_210000, so nothing truncates.
     */
    public const MAX_BODY_CHARS = 50000;

    /**
     * A3 (v1.7.2): switching an EXISTING prompt to image type without a
     * cover is a validation error. Create (type=image from scratch) and
     * edits that keep image stay optional — the founder decision scopes
     * the requirement to the switch itself.
     */
    private function isImageTypeSwitch(): bool
    {
        if ($this->input('type') !== Prompt::TYPE_IMAGE) {
            return false;
        }

        $route = $this->route('prompt');
        if (! $route instanceof Prompt) {
            return false; // create: optional
        }

        return $route->type !== Prompt::TYPE_IMAGE;
    }

    public function authorize(): bool
    {
        // Route-level policy checks (Gate::authorize) handle permission;
        // this request only validates shape.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:6', 'max:160'],
            'description' => ['required', 'string', 'min:40', 'max:600'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    // A2 (v1.7.2): server truth for the type-scoped picker —
                    // the category must belong to the chosen type (or be
                    // universal). Mirrors the client-side re-scoping.
                    ->where(fn ($inner) => $inner
                        ->whereNull('type_scope')
                        ->orWhere('type_scope', $this->input('type')))),
            ],
            'type' => ['required', 'string', 'in:'.implode(',', Prompt::TYPES)],
            // H1 (v1.7.3 hotfix): 4000 → MAX_BODY_CHARS (50,000) — see the const.
            'body' => ['required', 'string', 'min:30', 'max:'.self::MAX_BODY_CHARS],
            'tags' => ['required', 'string', 'min:2', 'max:200'],
            'recommended_tools' => ['required', 'array', 'min:1', 'max:4'],
            // T6 (v1.5.0): tools must be active registry entries whose
            // modality fits the chosen prompt type ('any' fits everything).
            // This was previously a bare string check — arbitrary tool names
            // slipped through and later broke logo rendering.
            'recommended_tools.*' => [
                'required',
                'string',
                'max:40',
                Rule::exists('tool_logos', 'name')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('modality', [$this->input('type'), 'any'])),
            ],
            'audience' => ['nullable', 'string', 'max:120'],
            'tips' => ['nullable', 'string', 'max:1500'],
            'changelog' => ['nullable', 'string', 'max:500'],
            'price_npr' => ['required', 'integer', 'min:0', 'max:50000'],
            'visibility' => ['required', 'string', 'in:'.Prompt::VISIBILITY_PUBLIC.','.Prompt::VISIBILITY_PRIVATE],
            // Cover art (A2/A3, v1.7.2): image prompts only — prohibited on
            // every other type; REQUIRED when the creator switches an existing
            // prompt onto image (founder decision for this release; the A3
            // rendered-route test locks it). GD re-encode validates harder in
            // the controller. 8 MB here is a pre-filter.
            'cover_image' => ['nullable', 'image', 'max:8192',
                Rule::when(fn () => $this->input('type') !== Prompt::TYPE_IMAGE, ['prohibited']),
                Rule::when(fn () => $this->isImageTypeSwitch(), ['required']),
            ],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Normalized, DB-ready field set shared by store and update.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $tags = collect(preg_split('/[,\n]/', (string) $this->input('tags')) ?: [])
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->take(self::MAX_TAGS)
            ->values()
            ->all();

        $tips = collect(preg_split('/\r\n|\r|\n/', (string) $this->input('tips')) ?: [])
            ->map(fn (string $tip) => trim($tip))
            ->filter()
            ->take(self::MAX_TIPS)
            ->values()
            ->all();

        $priceCents = max(0, (int) $this->input('price_npr')) * 100;

        return [
            'title' => trim((string) $this->input('title')),
            'description' => trim((string) $this->input('description')),
            'category_id' => (int) $this->input('category_id'),
            'type' => (string) $this->input('type'),
            'body' => (string) $this->input('body'),
            'tags' => $tags,
            'recommended_tools' => array_values((array) $this->input('recommended_tools', [])),
            'audience' => $this->filled('audience') ? trim((string) $this->input('audience')) : null,
            'tips' => $tips,
            'price_cents' => $priceCents,
            'license_tier' => $priceCents > 0 ? Prompt::LICENSE_COMMERCIAL : Prompt::LICENSE_PERSONAL,
            'visibility' => (string) $this->input('visibility'),
        ];
    }

    /** Denormalized Scout/FULLTEXT haystack. */
    public function searchText(): string
    {
        $fields = $this->fields();

        return implode(' ', array_filter([
            $fields['title'],
            $fields['description'],
            implode(' ', $fields['tags']),
            implode(' ', $fields['recommended_tools']),
        ]));
    }

    /** Collision-safe unique slug for a new listing. */
    public static function uniqueSlug(string $title, ?int $ignorePromptId = null): string
    {
        $base = Str::slug($title) ?: Str::slug(Str::random(8));
        $slug = $base;
        $attempt = 1;

        while (DB::table('prompts')
            ->when($ignorePromptId, fn ($query) => $query->where('id', '!=', $ignorePromptId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.(++$attempt);
        }

        return $slug;
    }
}
