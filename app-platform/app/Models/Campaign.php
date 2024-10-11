<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static create(array $array)
 * @method static findOrFail($id)
 */
class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'subject',
        'content',
        'type',
        'status',
        'scheduled_at',
    ];

    /**
     * @return BelongsToMany
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class, 'campaign_subscriber');
    }

    /**
     * @return HasMany
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }
}

