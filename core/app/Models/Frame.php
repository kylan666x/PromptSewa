<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * G3 (v1.7.0) — a cosmetic avatar ring. image_path is an alpha-PNG frame
 * variant (512px, transparency required). Removing a frame nulls
 * users.active_frame_id (nullOnDelete) — never breaks a user row.
 */
class Frame extends Model
{
    protected $fillable = [
        'name',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
