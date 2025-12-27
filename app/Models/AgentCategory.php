<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    /**
     * Get agents in this category.
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'category_id');
    }
}
