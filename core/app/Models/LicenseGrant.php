<?php

namespace App\Models;

use Database\Factories\LicenseGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseGrant extends Model
{
    /** @use HasFactory<LicenseGrantFactory> */
    use HasFactory;

    final public const STATUS_ACTIVE = 'active';

    final public const STATUS_REVOKED = 'revoked';

    /** S2 (v1.8.0): provenance for grants issued by the unlimited bypass. */
    final public const SOURCE_MEMBERSHIP_UNLIMITED = 'membership_unlimited';

    protected $fillable = [
        'user_id',
        'order_item_id',
        'issued_by',
        'issue_reason',
        'source',
        'prompt_id',
        'license_tier',
        'grant_code',
        'status',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Grants are entitlements: revoked, never deleted (AGENTS.md #5). */
    public function revoke(): void
    {
        $this->status = self::STATUS_REVOKED;
        $this->revoked_at = now();
        $this->save();
    }
}
