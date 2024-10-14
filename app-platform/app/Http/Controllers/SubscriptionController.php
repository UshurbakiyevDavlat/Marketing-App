<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;

class SubscriptionController extends Controller
{
    //todo move main logic to service layer and then make it more abstract with different implementations option.

    /**
     * Subscribe to plan
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function createSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is incorrect');
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'plan' => 'required|string|in:free,basic,pro,enterprise',
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));
        DB::beginTransaction();

        try {
            $customer = Customer::create([
                'email' => $user->email,
                'payment_method' => $validated['payment_method'],
                'invoice_settings' => [
                    'default_payment_method' => $validated['payment_method'],
                ],
            ]);

            $subscription = Subscription::create([
                'customer' => $customer->id,
                'items' => [['price' => $this->getPlanPriceId($validated['plan'])]],
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            $user->subscriptions()->create([
                'stripe_subscription_id' => $subscription->id,
                'stripe_customer_id' => $customer->id,
                'plan' => $validated['plan'],
                'user_id' => $user->id,
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $this->getPlanAmount($validated['plan']),
                'status' => 'pending',
            ]);

            $paymentIntent = $subscription->latest_invoice->payment_intent;

            if ($paymentIntent->status === 'succeeded') {
                $payment->update(['status' => 'completed']);
            } elseif ($paymentIntent->status === 'requires_payment_method') {
                $payment->update(['status' => 'failed']);
                throw new Exception('Payment failed. Please provide a valid payment method.');
            }

            DB::commit();

            return response()->json(['message' => 'Subscription created successfully'], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is incorrect');
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        $subscription = $user->activeSubscription;

        if ($subscription) {
            DB::beginTransaction();

            try {
                $stripeSubscription = Subscription::retrieve($subscription->stripe_subscription_id);
                $stripeSubscription->cancel();

                $subscription->update([
                    'ends_at' => now(), //todo maybe at the end of end subscription then
                    'cancel_reason' => $request->input('reason'),
                ]);

                DB::commit();

                return response()->json(['message' => 'Subscription cancelled successfully']);

            } catch (Exception $e) {
                DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        return response()->json(['error' => 'No active subscription found'], 404);
    }

    /**
     * Stripe price's ids for plan products
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
     * @param string $plan
     * @return float
     */
    private function getPlanAmount(string $plan): float
    {
        //todo move it somewhere, in db table for example
        $plans = [
            'free' => 0.0,
            'basic' => 15.0,
            'pro' => 50.0,
            'enterprise' => 200.0,
        ];

        return $plans[$plan];
    }
}

