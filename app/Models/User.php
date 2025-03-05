<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @method static find(int $int)
 * @method static create(array $array)
 * @method static findOrFail(int $userId)
 * @property mixed $id
 * @property mixed $email
 * @property mixed $name
 * @property mixed $role
 * @property mixed $subscriptions
 * @property mixed $activeSubscription
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Проверка, является ли пользователь администратором.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Проверка, является ли пользователь обычным пользователем.
     *
     * @return bool
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * @return HasMany
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    /**
     * @return HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasOne
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->whereNull('ends_at');
    }

    /**
     * @return SubscriptionPlan|null
     */
    public function currentPlan(): SubscriptionPlan|null
    {
        return $this->activeSubscription?->plan;
    }

    /**
     * @param string $featureName
     * @return bool
     */
    public function featureEnabled(string $featureName): bool
    {
        $plan = $this->currentPlan();

        if (!$plan instanceof SubscriptionPlan) {
            return false;
        }

        $feature = $plan->features()->where('feature_name', $featureName)->first();

        if ($feature && isset($feature->limits['active'])) {
            return (bool)$feature->limits['active'];
        }

        return false;
    }

    /**
     * @param string $featureName
     * @param string $limitKey
     * @return false|mixed|null
     * @throws Exception
     */
    public function getFeatureLimit(string $featureName, string $limitKey): mixed
    {
        $plan = $this->currentPlan();

        if (!$plan instanceof SubscriptionPlan) {
            return false;
        }

        $feature = $plan->features()->where('feature_name', $featureName)->first();

        if (!$feature instanceof PlanFeature){
            throw new Exception('There is no such feature in current plan');
        }

        return $feature->limits[$limitKey] ?? null;
    }
}
