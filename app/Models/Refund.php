<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(string[] $array)
 */
class Refund extends Model
{
    use HasFactory;

    protected $table = 'refunds';

    protected $fillable = [
        'provider_refund_id',
        'provider',
        'amount'
    ];
}
