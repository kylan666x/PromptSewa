<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * G2 (v1.7.0) — an achievement definition. image_path is an alpha-PNG
 * badge variant (512px, transparency required — never JPEG).
 */
class Badge extends Model
{
    public const CRITERIA = [
        'first_publish',
        'first_sale',
        'sales_10',
        'sales_50',
        'verified',
        'top_rated',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'criterion',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }
}
