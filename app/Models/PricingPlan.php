<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'price',
    'currency',
    'billing_cycle',
    'delivery_modes',
    'limits',
    'features',
    'highlights',
    'is_active',
    'is_featured',
    'cta_label',
    'sort_order',
])]
class PricingPlan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'delivery_modes' => 'array',
            'limits' => 'array',
            'features' => 'array',
            'highlights' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price')->orderBy('name');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(AdminRegistrationRequest::class);
    }
}
