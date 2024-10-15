<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get user's active subscription
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function getActiveSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is incorrect');
        }

        return response()->json(['subscription' => $user->activeSubscription ?? 'There is no active subscription']);
    }

    /**
     * Get user's subscriptions history
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function getSubscriptionHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is incorrect');
        }

        return response()->json(['history' => $user->subscriptions ?? 'There is no history']);
    }

    /**
     * Subscribe to plan
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'plan' => 'required|string|in:free,basic,pro,enterprise',
        ]);

        DB::beginTransaction();
        try {
            $subscription = $this->paymentService->createSubscription($user, $validated['payment_method'], $validated['plan']);

            Payment::create([
                'user_id' => $user->id,
                'amount' => $this->paymentService->getPlanAmount($validated['plan']),
                'status' => 'completed',
                'transaction_type' => 'income',
            ]);

            DB::commit();

            Log::info('Subscription created successfully for user: ' . $user->id, ['subscription' => $subscription]);

            return response()->json(['message' => 'Subscription created successfully', 'subscription' => $subscription], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating subscription for user: ' . $user->id, ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel current subscription plan
     *
     * @throws Exception
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is incorrect');
        }

        $cancelReason = $request->input('reason');
        $subscription = $user->activeSubscription;

        if (!$subscription instanceof Subscription) {
            throw new Exception('There is no active subscription');
        }

        DB::beginTransaction();
        try {
            $this->paymentService->cancelSubscription($subscription, $cancelReason);
            DB::commit();

            Log::info('Subscription canceled successfully for user: ' . $user->id, ['subscription' => $subscription]);

            return response()->json(['message' => 'Subscription was successfully canceled']);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error canceling subscription for user: ' . $user->id, ['error' => $exception->getMessage()]);

            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    /**
     * Возврат средств за подписку.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function refund(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $subscription = Subscription::find($validated['subscription_id']);

        if (!$subscription || $subscription->user_id !== $user->id) {
            throw new Exception('Subscription not found or does not belong to user.');
        }

        DB::beginTransaction();
        try {
            $this->paymentService->refund($subscription, $validated['amount']);

            DB::commit();

            Log::info('Refund processed successfully for user: ' . $user->id, ['subscription_id' => $subscription->id]);

            return response()->json(['message' => 'Refund processed successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing refund for user: ' . $user->id, ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
