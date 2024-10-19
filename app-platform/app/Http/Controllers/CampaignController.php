<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignEmails;
use App\Models\Campaign;
use App\Models\PlanFeature;
use App\Models\User;
use App\Services\CampaignEmailService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class CampaignController extends Controller
{
    protected CampaignEmailService $campaignEmailService;

    public function __construct(CampaignEmailService $campaignEmailService)
    {
        $this->campaignEmailService = $campaignEmailService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is not a valid');
        }

        $campaigns = $user?->campaigns()->with(['subscribers' => function ($query) {
            $query->select('subscribers.id', 'subscribers.email');
        }])->get();

        return response()->json($campaigns);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is not a valid');
        }
        // todo add validation request class and validate there
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:email,sms,push',
            'scheduled_at' => 'nullable|date',
        ]);

        if (!$user->featureEnabled('subscriber_management')) {
            return response()->json(['error' => 'Subscriber_management  is not available on your current plan.'], 403);
        }

        $subscriberLimit = $user->getFeatureLimit('subscriber_management', 'subscriber_limit');
        $currentSubscriberCount = $user->subscribers()->count();

        if ($subscriberLimit !== PlanFeature::EMAIL_LIMIT_UNLIM && $currentSubscriberCount >= $subscriberLimit) {
            return response()->json(['error' => 'Subscriber limit reached. Upgrade your plan for more subscribers.'], 403);
        }

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'content' => $validated['content'],
            'type' => $validated['type'],
            'status' => 'draft',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        return response()->json($campaign, 201);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        //todo research whether Laravel way to get model via argument is better
        $campaign = Campaign::findOrFail($id);
        return response()->json($campaign);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // todo research whether Laravel way to get model via argument is better
        // todo add validation request class and validate there
        $validated = $request->validate([
            'name' => 'string|max:255',
            'subject' => 'string|max:255',
            'content' => 'string',
            'type' => 'in:email,sms,push',
            'scheduled_at' => 'nullable|date',
        ]);

        if (!$validated) {
            throw new Exception('Nothing to update.');
        }

        $campaign = Campaign::findOrFail($id);
        $campaign->update($validated);

        return response()->json($campaign);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        //todo research whether Laravel way to get model via argument is better
        //todo added soft-update to Campaign migration and model
        $campaign = Campaign::findOrFail($id);
        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted successfully']);
    }

    /**
     * Отправка кампании.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is not a valid');
        }

        if (!$user->featureEnabled('email_sending')) {
            return response()->json(['error' => 'Email sending is not available on your current plan.'], 403);
        }

        $emailLimit = $user->getFeatureLimit('email_sending', 'email_limit');
        $sentEmails = $this->getSentEmailsCount($user->id);

        if ($emailLimit !== PlanFeature::EMAIL_LIMIT_UNLIM && $sentEmails >= $emailLimit) {
            return response()->json(['error' => 'Email limit reached. Upgrade your plan for more emails.'], 403);
        }

        $campaign = Campaign::findOrFail($id);
        $this->campaignEmailService->sendCampaign($campaign, $user);

        return response()->json(['message' => 'Campaign sent successfully']);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function attachSubscribers(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'subscriber_ids' => 'required|array',
            'subscriber_ids.*' => 'exists:subscribers,id',
        ]);

        $alreadyAttachedSubscribers = [];
        $newSubscribers = [];

        foreach ($validated['subscriber_ids'] as $subscriberId) {
            $isAlreadyAttached = $campaign->subscribers()->where('subscribers.id', $subscriberId)->exists();

            if ($isAlreadyAttached) {
                $alreadyAttachedSubscribers[] = $subscriberId;
            } else {
                $newSubscribers[] = $subscriberId;
            }
        }

        if (!empty($newSubscribers)) {
            $campaign->subscribers()->attach($newSubscribers);
        }

        return response()->json([
            'message' => 'Subscribers attached successfully',
            'already_was_attached' => $alreadyAttachedSubscribers,
            'newly_attached' => $newSubscribers,
        ]);
    }

    /**
     * Запланировать отправку кампании.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is not a valid');
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $campaign = Campaign::findOrFail($id);

        DB::beginTransaction();

        try {
            $campaign->update([
                'scheduled_at' => $validated['scheduled_at'],
                'status' => 'scheduled',
            ]);

            $scheduledAt = Carbon::parse($campaign->scheduled_at);

            Queue::later($scheduledAt, new SendCampaignEmails($campaign, $user, $this->campaignEmailService));
            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error sending campaign emails: ' . $e->getMessage());
            throw $e;
        }

        return response()->json(['message' => 'Campaign scheduled successfully'], 200);
    }

    /**
     * Создать кампанию с A/B тестированием и сразу отправить.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function createABTest(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is incorrect');
        }

        if (!$user->featureEnabled('ab_testing')) {
            return response()->json(['error' => 'A/B Testing is not available on your current plan.'], 403);
        }

        $validated = $request->validate([
            'subject_a' => 'required|string|max:255',
            'content_a' => 'required|string',
            'subject_b' => 'required|string|max:255',
            'content_b' => 'required|string',
            'subscriber_ids' => 'required|array',
            'subscriber_ids.*' => 'exists:subscribers,id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        //todo на будущее рассмотреть мб больше разных вариантов
        $campaignA = Campaign::create([
            'name' => $request->input('name') . ' (Variant A)',
            'subject' => $validated['subject_a'],
            'content' => $validated['content_a'],
            'status' => 'draft',
            'variant' => 'A',
            'user_id' => $user->id,
        ]);

        $campaignB = Campaign::create([
            'name' => $request->input('name') . ' (Variant B)',
            'subject' => $validated['subject_b'],
            'content' => $validated['content_b'],
            'status' => 'draft',
            'variant' => 'B',
            'user_id' => $user->id,
        ]);

        $subscriberIds = $validated['subscriber_ids'];
        $campaignA->subscribers()->attach($subscriberIds);
        $campaignB->subscribers()->attach($subscriberIds);

        if (isset($validated['scheduled_at'])) {
            Queue::later(Carbon::parse($validated['scheduled_at']), new SendCampaignEmails($campaignA, $user, $this->campaignEmailService));
            Queue::later(Carbon::parse($validated['scheduled_at']), new SendCampaignEmails($campaignB, $user, $this->campaignEmailService));
        } else {
            $this->campaignEmailService->sendABTest($campaignA, $campaignB, $user);
        }

        return response()->json([
            'message' => 'A/B test created and sent successfully',
            'campaign_a' => $campaignA,
            'campaign_b' => $campaignB,
        ], 201);
    }

    /**
     * todo move to somewhere else
     * @param int $userId
     * @return int
     */
    private function getSentEmailsCount(int $userId): int
    {
        return Campaign::where('user_id', $userId)
            ->where('status', 'sent')
            ->sum('id');
    }
}

