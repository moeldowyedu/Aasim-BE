<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    use HasFactory;

    /**
     * Composite primary key.
     *
     * @var array
     */
    protected $primaryKey = ['tenant_id', 'user_id'];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'role',
        'invited_at',
        'joined_at',
        'left_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Status constants.
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INVITED = 'invited';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_LEFT = 'left';

    /**
     * Legacy role constants (use Spatie roles instead).
     */
    const ROLE_OWNER = 'owner';
    const ROLE_ADMIN = 'admin';
    const ROLE_MEMBER = 'member';

    /**
     * Get the tenant that owns the membership.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the user that owns the membership.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the membership is an owner role.
     */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Check if the membership is an admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if the membership is a member role.
     */
    public function isMember(): bool
    {
        return $this->role === self::ROLE_MEMBER;
    }

    /**
     * Check if the membership is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the membership is invited (pending).
     */
    public function isInvited(): bool
    {
        return $this->status === self::STATUS_INVITED;
    }

    /**
     * Check if the membership is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if the user has left the tenant.
     */
    public function hasLeft(): bool
    {
        return $this->status === self::STATUS_LEFT;
    }

    /**
     * Activate the membership.
     */
    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'joined_at' => $this->joined_at ?? now(),
        ]);
    }

    /**
     * Suspend the membership.
     */
    public function suspend(): void
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    /**
     * Mark the membership as left.
     */
    public function leave(): void
    {
        $this->update([
            'status' => self::STATUS_LEFT,
            'left_at' => now(),
        ]);
    }
}
