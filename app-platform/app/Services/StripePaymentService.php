<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripePaymentService implements PaymentServiceInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Создание подписки через Stripe.
     *
     * @param User $user
     * @param string $paymentMethod
     * @param string $plan
     * @return Subscription
     * @throws Exception
     */
    public function createSubscription(User $user, string $paymentMethod, string $plan): Subscription
    {
        DB::beginTransaction();
        try {
            $customer = Customer::create([
                'email' => $user->email,
                'payment_method' => $paymentMethod,
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethod,
                ],
            ]);

            $stripeSubscription = StripeSubscription::create([
                'customer' => $customer->id,
                'items' => [['price' => $this->getPlanPriceId($plan)]],
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_customer_id' => $customer->id,
                'plan' => $plan,
            ]);

            Payment::create([
                'user_id' => $user->id,
                'amount' => $this->getPlanAmount($plan),
                'status' => 'completed',
                'transaction_type' => 'income',
            ]);

            DB::commit();
            Log::info('Subscription created via Stripe', ['subscription_id' => $subscription->stripe_subscription_id]);

            return $subscription;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating Stripe subscription for user: ' . $user->id, ['error' => $e->getMessage()]);

            throw new Exception('Error creating subscription: ' . $e->getMessage());
        }
    }

    /**
     * Отмена подписки через Stripe.
     *
     * @param Subscription $subscription
     * @param string|null $reason
     * @return bool
     * @throws Exception
     */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): bool
    {
        DB::beginTransaction();
        try {
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
            $stripeSubscription->cancel();

            $subscription->update([
                'ends_at' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                'cancel_reason' => $reason,
            ]);

            Payment::create([
                'user_id' => $subscription->user_id,
                'amount' => 0, // not refund
                'status' => 'completed',
                'transaction_type' => 'outcome',
            ]);

            DB::commit();
            Log::info('Subscription canceled via Stripe', ['subscription_id' => $subscription->stripe_subscription_id]);

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error canceling Stripe subscription', ['subscription_id' => $subscription->stripe_subscription_id, 'error' => $e->getMessage()]);

            throw new Exception('Error cancelling subscription: ' . $e->getMessage());
        }
    }

    /**
     * Возврат средств за подписку через Stripe.
     *
     * @param Subscription $subscription
     * @param float $amount
     * @return bool
     * @throws Exception
     */
    public function refund(Subscription $subscription, float $amount): bool
    {
        $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
        $latestInvoice = Invoice::retrieve($stripeSubscription->latest_invoice);
        $paymentIntent = PaymentIntent::retrieve($latestInvoice->payment_intent);
        $chargeId = $paymentIntent->latest_charge;

        DB::beginTransaction();
        try {
            Refund::create([
                'charge' => $chargeId,
                'amount' => $amount * 100,  // В Stripe сумма указывается в центах
            ]);

            Payment::create([
                'user_id' => $subscription->user_id,
                'amount' => $amount,
                'status' => 'completed',
                'transaction_type' => 'outcome',
            ]);

            DB::commit();
            Log::info('Refund processed via Stripe', ['subscription_id' => $subscription->stripe_subscription_id, 'amount' => $amount]);

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing refund via Stripe', ['subscription_id' => $subscription->stripe_subscription_id, 'error' => $e->getMessage()]);

            throw new Exception('Error processing refund: ' . $e->getMessage());
        }
    }

    /**
     * Получение ID цены плана.
     *
     * @param string $plan
     * @return string
     */
    private function getPlanPriceId(string $plan): string
    {
        $plans = [
            'free' => 'price_1Q9rsbKjxe7OpAXXPdFVz756',
            'basic' => 'price_1Q9ruqKjxe7OpAXXVcUIgndD',
            'pro' => 'price_1Q9rwCKjxe7OpAXXwWLfA0kn',
            'enterprise' => 'price_1Q9rx2Kjxe7OpAXXMHZzacYf',
        ];

        return $plans[$plan];
    }

    /**
     * Получение суммы для плана.
     *
     * @param string $plan
     * @return float
     */
    public function getPlanAmount(string $plan): float
    {
        $plans = [
            'free' => 0.0,
            'basic' => 15.0,
            'pro' => 50.0,
            'enterprise' => 200.0,
        ];

        return $plans[$plan];
    }
}
