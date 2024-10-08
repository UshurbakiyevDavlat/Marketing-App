<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @method static create(array $array)
 * @method static updateOrCreate(array $array, array $array1)
 */
class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs';

    protected $fillable = [
        'campaign_id',
        'email',
        'status',
        'event'
    ];

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'email_log_campaign');
    }
}
