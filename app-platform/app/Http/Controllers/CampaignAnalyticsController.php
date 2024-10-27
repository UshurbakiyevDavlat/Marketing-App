<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\User;
use App\Services\CampaignEmailService;
use Exception;
use Illuminate\Http\JsonResponse;

class CampaignAnalyticsController extends Controller
{
    public CampaignEmailService $campaignEmailService;

    public function __construct(CampaignEmailService $campaignEmailService)
    {
        $this->campaignEmailService = $campaignEmailService;
    }

    /**
     * Получение аналитики для конкретной кампании
     *
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function getCampaignAnalytics(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $metrics = $this->campaignEmailService->getMetricsForCampaign($campaign->id);

        if (is_null($metrics)) {
            return $this->emptyMetricsResponse($id);
        }

        return $this->formatMetricsResponse($metrics, $id);
    }

    /**
     * Получение общей аналитики для всех кампаний пользователя
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function getOverallAnalytics(): JsonResponse
    {
        $user = auth()->user();

        if (!$user instanceof User) {
            throw new Exception('User is not valid');
        }

        $metrics = $this->campaignEmailService->getMetricsForUser($user->id);

        if (is_null($metrics)) {
            return $this->emptyMetricsResponse();
        }

        return $this->formatMetricsResponse($metrics);
    }

    /**
     * Форматирование метрик и возврат JSON ответа
     *
     * @param array $metrics
     * @param int|null $campaignId
     * @return JsonResponse
     * @throws Exception
     */
    private function formatMetricsResponse(array $metrics, int $campaignId = null): JsonResponse
    {
        $totalSent = $metrics['total'] ?? 0;
        $deliveredCount = $metrics['delivered'] ?? 0;
        $openCount = $metrics['opened'] ?? 0;
        $clickCount = $metrics['clicked'] ?? 0;
        $unsubscribeCount = $metrics['unsubscribed'] ?? 0;
        $bounceCount = $metrics['bounced'] ?? 0;
        $uniqueOpens = $metrics['unique_opens'] ?? 0;
        $uniqueClicks = $metrics['unique_clicks'] ?? 0;
        $conversions = $metrics['conversions'] ?? 0;

        // Новые метрики
        $openRate = $this->calculateRate($uniqueOpens, $deliveredCount);
        $clickRate = $this->calculateRate($uniqueClicks, $deliveredCount);
        $bounceRate = $this->calculateRate($bounceCount, $totalSent);
        $unsubscribeRate = $this->calculateRate($unsubscribeCount, $deliveredCount);
        $conversionRate = $this->calculateRate($conversions, $uniqueClicks);

        $responseBody = [
            'total_sent' => $totalSent,
            'delivered' => $deliveredCount,
            'opens' => $openCount,
            'unique_opens' => $uniqueOpens,
            'clicks' => $clickCount,
            'unique_clicks' => $uniqueClicks,
            'unsubscribes' => $unsubscribeCount,
            'bounces' => $bounceCount,
            'conversions' => $conversions,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'bounce_rate' => $bounceRate,
            'unsubscribe_rate' => $unsubscribeRate,
            'conversion_rate' => $conversionRate,
        ];

        if ($campaignId) {
            $responseBody['campaign_id'] = $campaignId;
        }

        return response()->json($responseBody);
    }


    /**
     * Возвращаем пустой ответ с нулевыми метриками
     *
     * @param int|null $campaignId
     * @return JsonResponse
     */
    private function emptyMetricsResponse(int $campaignId = null): JsonResponse
    {
        $resultBody = [
            'total_sent' => 0,
            'delivered' => 0,
            'opens' => 0,
            'clicks' => 0,
            'unsubscribes' => 0,
            'bounces' => 0,
            'open_rate' => 0,
            'click_rate' => 0,
        ];

        if ($campaignId) {
            $resultBody['campaign_id'] = $campaignId;
        }

        return response()->json($resultBody);
    }

    /**
     * Расчет процента метрики (например, open_rate, click_rate)
     *
     * @param int $part
     * @param int $total
     * @return float
     */
    private function calculateRate(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0;
    }

    /**
     * Определить победителя A/B теста по метрикам.
     *
     * @param int $campaignAId
     * @param int $campaignBId
     * @return JsonResponse
     */
    public function determineABTestWinner(int $campaignAId, int $campaignBId): JsonResponse
    {
        $resultData = $this->campaignEmailService->determineABTestWinner($campaignAId, $campaignBId);

        $campaignAMetrics = $resultData['campaignAMetrics'];
        $campaignBMetrics = $resultData['campaignBMetrics'];
        $winner = $resultData['winner'];

        return response()->json([
            'campaign_a' => $campaignAMetrics,
            'campaign_b' => $campaignBMetrics,
            'winner' => $winner,
        ]);
    }
}
