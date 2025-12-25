<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'tier',
        'price_monthly',
        'price_annual',
        'features',
        'highlight_features',
        'limits',
        'max_users',
        'max_agents',
        'storage_gb',
        'is_active',
        'is_published',
        'is_archived',
        'plan_version',
        'parent_plan_id',
        'display_order',
        'trial_days',
        'description',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'array',
        'highlight_features' => 'array',
        'limits' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'is_archived' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_annual' => 'decimal:2',
    ];

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get the parent plan (for versioned plans).
     */
    public function parentPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'parent_plan_id');
    }

    /**
     * Get child plans (plan versions).
     */
    public function childPlans()
    {
        return $this->hasMany(SubscriptionPlan::class, 'parent_plan_id');
    }

    /**
     * Check if plan is free tier.
     */
    public function isFree(): bool
    {
        return $this->tier === 'free';
    }

    /**
     * Check if plan is for personal use.
     */
    public function isPersonal(): bool
    {
        return $this->type === 'personal';
    }

    /**
     * Check if plan is for organizations.
     */
    public function isOrganization(): bool
    {
        return $this->type === 'organization';
    }

    /**
     * Get monthly price or 0 if free.
     */
    public function getMonthlyPriceAttribute($value)
    {
        return $value ?? 0;
    }

    /**
     * Get annual price or 0 if free.
     */
    public function getAnnualPriceAttribute($value)
    {
        return $value ?? 0;
    }

    /**
     * Calculate annual savings percentage.
     */
    public function getAnnualSavingsPercentage(): int
    {
        if (!$this->price_monthly || !$this->price_annual) {
            return 0;
        }

        $monthlyYearly = $this->price_monthly * 12;
        $savings = $monthlyYearly - $this->price_annual;

        return (int) round(($savings / $monthlyYearly) * 100);
    }

    /**
     * Check if plan is published and available for selection.
     */
    public function isPublished(): bool
    {
        return $this->is_published && $this->is_active && !$this->is_archived;
    }

    /**
     * Check if plan is archived.
     */
    public function isArchived(): bool
    {
        return $this->is_archived;
    }

    /**
     * Scope to get only published plans.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where('is_active', true)
            ->where('is_archived', false);
    }

    /**
     * Scope to get plans by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('is_archived', false);
    }

    /**
     * Get the display name with tier.
     */
    public function getDisplayName(): string
    {
        return "{$this->name} ({$this->tier})";
    }

    /**
     * Count active subscriptions for this plan.
     */
    public function activeSubscriptionsCount(): int
    {
        return $this->subscriptions()
            ->whereIn('status', ['trialing', 'active'])
            ->count();
    }
}