<?php

namespace App\Models;

use Database\Factories\PromptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Prompt extends Model
{
    /** @use HasFactory<PromptFactory> */
    use HasFactory, Searchable, SoftDeletes;

    final public const STATUS_DRAFT = 'draft';

    final public const STATUS_PENDING = 'pending';

    final public const STATUS_PUBLISHED = 'published';

    final public const STATUS_REJECTED = 'rejected';

    final public const VISIBILITY_PUBLIC = 'public';

    final public const VISIBILITY_PRIVATE = 'private';

    final public const LICENSE_PERSONAL = 'personal';

    final public const LICENSE_COMMERCIAL = 'commercial';

    /**
     * Prompt kinds (God of Prompt research): drive dynamic placeholders in
     * the create/edit flow and layout on detail pages.
     */
    final public const TYPE_TEXT = 'text';

    final public const TYPE_IMAGE = 'image';

    final public const TYPE_VIDEO = 'video';

    /** @var list<string> */
    final public const TYPES = [self::TYPE_TEXT, self::TYPE_IMAGE, self::TYPE_VIDEO];

    protected $fillable = [
        'user_id',
        'category_id',
        'forked_from_prompt_id',
        'title',
        'slug',
        'description',
        'cover_image_path',
        'visibility',
        'search_text',
        'license_tier',
        'price_cents',
        'status',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'download_count' => 'integer',
            'fork_count' => 'integer',
            'upvotes' => 'integer',
            'downvotes' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Scout search abstraction (Sprint 1 gate condition #1).
    //
    // Controllers must call Prompt::search($term) — never raw MATCH()/LIKE.
    // Since UI-001 the default SCOUT_DRIVER is "database": LIKE on
    // SQLite/local, MySQL FULLTEXT on cPanel, with zero app-code changes.
    // Phase 4: swap SCOUT_DRIVER to meilisearch for vector-capable search.
    // ------------------------------------------------------------------

    /**
     * The index name for the Scout engine.
     */
    public function searchableAs(): string
    {
        return 'prompts_index';
    }

    /**
     * Data sent to the search engine.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'description' => $this->description,
            'search_text' => $this->search_text,
            'category_id' => $this->category_id,
            'type' => $this->type,
        ];
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The prompt this one was forked from (null = original work). */
    public function forkParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forked_from_prompt_id');
    }

    /** Forks of this prompt. */
    public function forks(): HasMany
    {
        return $this->hasMany(self::class, 'forked_from_prompt_id');
    }

    /** Immutable version history, oldest → newest. */
    public function versions(): HasMany
    {
        return $this->hasMany(PromptVersion::class)->orderBy('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(PromptVersion::class)->orderByDesc('version_number');
    }

    /** The sellable product backing this listing (MKT-001). */
    public function product(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // ------------------------------------------------------------------
    // Query scopes (used by marketplace listings)
    // ------------------------------------------------------------------

    public function scopePublished($query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * UI-001: the exact contract for anything shown on a public surface —
     * published review state AND public visibility. Every storefront,
     * category and search query must apply this scope.
     */
    public function scopePublicListing($query): void
    {
        $query->where('status', self::STATUS_PUBLISHED)
            ->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /** Human-readable price for cards/detail pages (integer paisa → NPR string). */
    public function priceLabel(): string
    {
        return $this->price_cents === 0
            ? 'Free'
            : 'Rs. '.number_format(intdiv($this->price_cents, 100));
    }

    /** True when this listing is an image-generation prompt. */
    public function isImageType(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    /** True when this listing is a video-generation prompt. */
    public function isVideoType(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    /** Human label used in forms and detail pages. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_IMAGE => 'Image prompt',
            self::TYPE_VIDEO => 'Video prompt',
            default => 'Text prompt',
        };
    }

    /** True when this prompt may be shown to the given (possibly guest) viewer. */
    public function isViewableBy(?User $user): bool
    {
        if ($this->status === self::STATUS_PUBLISHED && $this->visibility === self::VISIBILITY_PUBLIC) {
            return true;
        }

        return $user !== null && $user->id === $this->user_id;
    }
}
