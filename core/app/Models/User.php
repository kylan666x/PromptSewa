<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** RBAC roles (PRD §5). Guests are simply unauthenticated visitors. */
    final public const ROLE_MEMBER = 'member';

    final public const ROLE_CREATOR = 'creator';

    final public const ROLE_MODERATOR = 'moderator';

    final public const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_verified',
        'is_official',
        'banned_at',
        'xp',
        'active_frame_id',
        'xp',
        'banner_path',
        'avatar_path',
        'bio',
        'payout_handle',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'xp' => 'integer',
            'is_verified' => 'boolean',
            'is_official' => 'boolean',
            'banned_at' => 'datetime',
            'xp' => 'integer',
        ];
    }

    // ------------------------------------------------------------------
    // RBAC helpers (PRD §5). Authorization gates build on these.
    // ------------------------------------------------------------------

    public function isAtLeast(string $role): bool
    {
        $hierarchy = [
            self::ROLE_MEMBER => 0,
            self::ROLE_CREATOR => 1,
            self::ROLE_MODERATOR => 2,
            self::ROLE_ADMIN => 3,
        ];

        return ($hierarchy[$this->role] ?? -1) >= ($hierarchy[$role] ?? PHP_INT_MAX);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isModerator(): bool
    {
        return $this->isAtLeast(self::ROLE_MODERATOR);
    }

    // ------------------------------------------------------------------
    // Ban/disable (A4). A banned account cannot log in, but keeps its
    // prompts, orders and license ledger — bans are an access decision,
    // not content destruction.
    // ------------------------------------------------------------------

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /** G2 (v1.7.0): earned badges (pivot rows). */
    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /** W4 (v1.7.3): earned/manual frame unlocks (award ledger rows). */
    public function frameUnlocks(): HasMany
    {
        return $this->hasMany(\App\Models\UserFrameUnlock::class);
    }

    /** G3 (v1.7.0): the equipped avatar frame, if any. */
    public function activeFrame()
    {
        return $this->belongsTo(Frame::class, 'active_frame_id');
    }

    public function promptVersions(): HasMany
    {
        return $this->hasMany(PromptVersion::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function licenseGrants(): HasMany
    {
        return $this->hasMany(LicenseGrant::class);
    }

    // ------------------------------------------------------------------
    // Public handle (username) — used for /creators/{username} URLs.
    // Falls back to the display name so legacy accounts without a chosen
    // handle keep their profile URL working. Binding for {creator} is
    // scoped in AppServiceProvider (username OR name); admin {user:id}
    // routes pin the id explicitly and are unaffected by the override.
    // ------------------------------------------------------------------

    public function getRouteKey(): string
    {
        return ($this->username !== null && $this->username !== '')
            ? $this->username
            : $this->name;
    }

    public function creatorRouteKey(): string
    {
        return $this->getRouteKey();
    }
}
