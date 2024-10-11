<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;

class CampaignAnalyticsController extends Controller
{
    const string RAW_AGGREGATE_METRIC_CONDITION = '
        COUNT(*) as total,
        SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = "opened" THEN 1 ELSE 0 END) as opened,
        SUM(CASE WHEN status = "clicked" THEN 1 ELSE 0 END) as clicked,
        SUM(CASE WHEN status = "unsubscribed" THEN 1 ELSE 0 END) as unsubscribed,
        SUM(CASE WHEN status = "bounced" THEN 1 ELSE 0 END) as bounced
    ';

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
        $metrics = $this->getMetricsForCampaign($campaign->id);

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

        $metrics = $this->getMetricsForUser($user->id);

        if (is_null($metrics)) {
            return $this->emptyMetricsResponse();
        }

        return $this->formatMetricsResponse($metrics);
    }

    /**
     * Получение метрик для пользователя
     *
     * @param int $userId
     * @return array|null
     */
    private function getMetricsForUser(int $userId): ?array
    {
        return EmailLog::whereHas('campaign', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->selectRaw(self::RAW_AGGREGATE_METRIC_CONDITION)
            ->first()
            ?->toArray();
    }

    /**
     * Получение метрик для конкретной кампании
     *
     * @param int $campaignId
     * @return array|null
     */
    private function getMetricsForCampaign(int $campaignId): ?array
    {
        return EmailLog::where('campaign_id', $campaignId)
            ->selectRaw(self::RAW_AGGREGATE_METRIC_CONDITION)
            ->first()
            ?->toArray();
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

        $openRate = $this->calculateRate($openCount, $deliveredCount);
        $clickRate = $this->calculateRate($clickCount, $deliveredCount);

        $responseBody = [
            'total_sent' => $totalSent,
            'delivered' => (int)$deliveredCount,
            'opens' => (int)$openCount,
            'clicks' => (int)$clickCount,
            'unsubscribes' => (int)$unsubscribeCount,
            'bounces' => (int)$bounceCount,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
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
}
