<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CampaignController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = User::find(1);//todo $request->user();
        $campaigns = $user->campaigns;
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
            'user_id' => 1, //todo $request->user()->id,
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
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function send(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->type !== 'email') {
            throw new Exception('Only email campaigns can be sent currently');
        }

        //todo make it correctly, it doesn't work for now, need to integrate Mailer api
        Mail::raw($campaign->content, function ($message) use ($campaign) {
            $message->to('dushurbakiev@gmail.com')
            ->subject($campaign->subject);
        });

        $campaign->status = 'sent'; //todo need const and update via update eloquent method
        $campaign->save();

        return response()->json(['message' => 'Campaign sent successfully']);
    }
}

