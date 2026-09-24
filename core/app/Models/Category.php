<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'type_scope',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /** Active categories only — inactive ones 404 on public pages. */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Categories selectable for a prompt type: universal ones (null scope)
     * plus categories scoped to that exact type.
     */
    public function scopeSelectableFor($query, string $type): void
    {
        $query->where(function ($inner) use ($type) {
            $inner->whereNull('type_scope')->orWhere('type_scope', $type);
        });
    }
}
