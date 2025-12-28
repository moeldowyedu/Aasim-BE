<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_user_id',
        'target_user_id',
        'tenant_id',
        'token_hash',
        'started_at',
        'ended_at',
        'expires_at',
        'ip_address',
        'reason',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * Get the admin user who started the impersonation.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Get the target user being impersonated.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Get the tenant being impersonated.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Check if impersonation is active.
     */
    public function isActive(): bool
    {
        return $this->started_at && !$this->ended_at && !$this->isExpired();
    }

    /**
     * Check if impersonation token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get duration in minutes.
     */
    public function getDurationMinutes(): ?int
    {
        if (!$this->ended_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->ended_at);
    }

    /**
     * End the impersonation session.
     */
    public function endSession(): void
    {
        $this->update([
            'ended_at' => now(),
        ]);
    }

    /**
     * Scope: Active impersonations.
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('started_at')
            ->whereNull('ended_at');
    }

    /**
     * Scope: By admin.
     */
    public function scopeByAdmin($query, int $adminId)
    {
        return $query->where('admin_user_id', $adminId);
    }

    /**
     * Scope: Recent logs.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Not expired.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: By tenant.
     */
    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Generate impersonation token.
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash the token for storage.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Verify token against hash.
     */
    public function verifyToken(string $token): bool
    {
        return hash_equals($this->token_hash, self::hashToken($token));
    }

    /**
     * Log a new impersonation session.
     */
    public static function startImpersonation(
        string $adminUserId,
        string $tenantId,
        ?string $reason = null,
        ?array $metadata = null,
        int $ttlMinutes = 30
    ): array {
        $token = self::generateToken();
        $tokenHash = self::hashToken($token);

        $log = self::create([
            'admin_user_id' => $adminUserId,
            'tenant_id' => $tenantId,
            'token_hash' => $tokenHash,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'ip_address' => request()->ip(),
            'reason' => $reason,
            'metadata' => $metadata,
        ]);

        return [
            'log' => $log,
            'token' => $token,
        ];
    }

    /**
     * Find active impersonation by token.
     */
    public static function findByToken(string $token): ?self
    {
        $tokenHash = self::hashToken($token);

        return self::where('token_hash', $tokenHash)
            ->active()
            ->notExpired()
            ->first();
    }
}