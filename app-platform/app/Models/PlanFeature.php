<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    use HasFactory;

    use HasFactory;

    protected $fillable = ['plan_id', 'feature_name', 'limits'];

    /**
     * @return BelongsTo
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * @param $value
     * @return mixed
     */
    public function getLimitsAttribute($value): mixed
    {
        return json_decode($value, true);
    }
}
