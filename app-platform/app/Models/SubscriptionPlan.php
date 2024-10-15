<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static create(array $array)
 * @method static find(int $plan_id)
 * @method static where(string $string, int $plan_id)
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'status'
    ];

    /**
     * @return HasMany
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_id');
    }
}
