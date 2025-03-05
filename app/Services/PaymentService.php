<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Exception;

class PaymentService
{
    protected PaymentServiceInterface $paymentService;

    public function __construct(PaymentServiceInterface $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Создать подписку для пользователя.
     *
     * @param User $user
     * @param string $paymentMethod
     * @param int $plan_id
     * @return Subscription
     */
    public function createSubscription(User $user, string $paymentMethod, int $plan_id): Subscription
    {
        return $this->paymentService->createSubscription($user, $paymentMethod, $plan_id);
    }

    /**
     * Отменить подписку пользователя.
     *
     * @param Subscription $subscription
     * @param string|null $reason
     * @return bool
     * @throws Exception
     */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): bool
    {
        return $this->paymentService->cancelSubscription($subscription, $reason);
    }

    /**
     * Вернуть средства за подписку.
     *
     * @param Subscription $subscription
     * @param float $amount
     * @return bool
     * @throws Exception
     */
    public function refund(Subscription $subscription, float $amount): bool
    {
        return $this->paymentService->refund($subscription, $amount);
    }
}
