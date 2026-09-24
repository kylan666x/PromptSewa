<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key-value runtime settings store. Admin-managed via the admin panel.
 *
 * Secret values (payment gateway keys) are encrypted with
 * Crypt::encryptString() BEFORE they reach this table (AGENTS.md #9) —
 * the model never stores or returns plaintext secrets to views.
 */
class Setting extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];
}
