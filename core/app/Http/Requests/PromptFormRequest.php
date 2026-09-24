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
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'type' => ['required', 'string', 'in:'.implode(',', Prompt::TYPES)],
            'body' => ['required', 'string', 'min:30', 'max:4000'],
            'tags' => ['required', 'string', 'min:2', 'max:200'],
            'recommended_tools' => ['required', 'array', 'min:1', 'max:4'],
            'recommended_tools.*' => ['required', 'string', 'max:40'],
            'audience' => ['nullable', 'string', 'max:120'],
            'tips' => ['nullable', 'string', 'max:1500'],
            'changelog' => ['nullable', 'string', 'max:500'],
            'price_npr' => ['required', 'integer', 'min:0', 'max:50000'],
            'visibility' => ['required', 'string', 'in:'.Prompt::VISIBILITY_PUBLIC.','.Prompt::VISIBILITY_PRIVATE],
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
