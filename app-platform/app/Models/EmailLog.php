<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 * @method static updateOrCreate(array $array, array $array1)
 * @method static where(string $string, int $id)
 * @method static whereHas(string $string, \Closure $param)
 */
class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs';

    protected $fillable = [
        'campaign_id',
        'email',
        'status',
        'event',
        'bounce_reason',
        'tags'
    ];

    protected $casts = [
        'tag' => 'array',
    ];

    /**
     * EmailLog принадлежит Campaign
     * @return BelongsTo
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
