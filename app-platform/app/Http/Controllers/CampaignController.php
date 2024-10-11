<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SendGrid\Mail\Mail;
use SendGrid\Mail\TypeException;

class CampaignController extends Controller
{
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
     */
    public function store(Request $request): JsonResponse
    {
        // todo add validation request class and validate there
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:email,sms,push',
            'scheduled_at' => 'nullable|date',
        ]);

        $campaign = Campaign::create([
            'user_id' => $request->user()->getAuthIdentifier(),
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'content' => $validated['content'],
            'type' => $validated['type'],
            'status' => 'draft',
            'scheduled_at' => $validated['scheduled_at'],
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
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws TypeException
     * @throws Exception
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new Exception('User is not a valid');
        }

        $campaign = Campaign::findOrFail($id);

        if ($campaign->status === 'sent') {
            throw new Exception('Campaign already sent.');
        }

        if ($campaign->type !== 'email') {
            throw new Exception('Only email campaigns can be sent currently');
        }

        $subscribers = $campaign->subscribers;

        if ($subscribers->isEmpty()) {
            return response()->json(['message' => 'No subscribers to send the campaign to'], 400);
        }

        $sendgrid = new \SendGrid(config('mail.mailers.sendgrid.api_key'));

        foreach ($subscribers as $subscriber) {
            $email = new Mail();
            $email->setFrom($user->email, $user->name);
            $email->setSubject($campaign->subject);
            $email->addTo($subscriber->email, $subscriber->name ?? 'Dear Subscriber');
            $email->addContent("text/plain", $campaign->content);
            $email->addContent("text/html", "<strong>" . $campaign->content . "</strong>");

            $email->addCustomArg('campaign_id', "$campaign->id");

            try {
                $response = $sendgrid->send($email);

                if ($response->statusCode() != 202) {
                    Log::error('Sendgrid response error-log', ['response' => $response->body()]);
                    continue;
                }

                Log::info('Sendgrid response log', [
                    'subscriber' => $subscriber->email,
                    'response' => $response->body()
                ]);

                EmailLog::updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'email' => $subscriber->email
                    ],
                    [
                        'status' => 'delivered',
                        'event' => 'Email sent successfully'
                    ]);
            } catch (Exception $e) {
                Log::error('Sendgrid exception', [
                    'subscriber' => $subscriber->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $campaign->update(['status' => 'sent']);

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
}

