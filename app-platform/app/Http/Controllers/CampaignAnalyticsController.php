<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\User;
use App\Notifications\ABTestWinnerNotification;
use App\Services\CampaignAnalyticsService;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Support\Facades\Log;

class CampaignAnalyticsController extends Controller
{
    private CampaignAnalyticsService $analyticsService;

    public function __construct(CampaignAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function getCampaignAnalytics(int $id): JsonResponse
    {
        try {
            $metrics = $this->analyticsService->getCampaignMetrics($id);
            return response()->json(['metrics' => $metrics]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error fetching campaign analytics'], 500);
        }
    }

    /**
     * @return JsonResponse
     */
    public function getOverallAnalytics(): JsonResponse
    {
        try {
            $user = auth()->user();
            $metrics = $this->analyticsService->getUserMetrics($user->id);
            return response()->json(['metrics' => $metrics]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error fetching overall analytics'], 500);
        }
    }

    /**
     * @param int $campaignAId
     * @param int $campaignBId
     * @return JsonResponse
     */
    public function determineABTestWinner(int $campaignAId, int $campaignBId): JsonResponse
    {
        $user = auth()->user();
        if (!$user instanceof User) {
            throw new RecordNotFoundException('User not found');
        }

        try {
            $result = $this->analyticsService->determineABTestWinner($campaignAId, $campaignBId);
            if ($result['winner'] instanceof Campaign) {
                $user->notify(new ABTestWinnerNotification($result['winner']));
            }
        } catch (Exception $e) {
            Log::error('ab test winner exception: ' . $e->getMessage());
            return response()->json(['error' => 'Error determining A/B test winner'], 500);
        }

        return response()->json($result);
    }
}
