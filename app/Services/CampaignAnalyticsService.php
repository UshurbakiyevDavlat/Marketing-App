<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\User;

class CampaignAnalyticsService
{
    const string TEST_A = 'A';
    const string TEST_B = 'B';

    const string TIE = 'Tie';

    /**
     * @param int $campaignId
     * @return int[]|null
     */
    public function getCampaignMetrics(int $campaignId): ?array
    {
        $campaign = Campaign::findOrFail($campaignId);
        return $this->calculateMetrics($campaign);
    }

    /**
     * @param int $userId
     * @return array|null
     */
    public function getUserMetrics(int $userId): ?array
    {
        $user = User::findOrFail($userId);

        $campaigns = $user->campaigns;
        return $this->aggregateMetrics($campaigns);
    }

    /**
     * @param int $campaignAId
     * @param int $campaignBId
     * @return array
     */
    public function determineABTestWinner(int $campaignAId, int $campaignBId): array
    {
        $metricsA = $this->getCampaignMetrics($campaignAId);
        $metricsB = $this->getCampaignMetrics($campaignBId);
        $winner = $this->compareABMetrics($metricsA, $metricsB);

        $winner = match ($winner) {
            self::TEST_A => Campaign::findOrFail($campaignAId),
            self::TEST_B => Campaign::findOrFail($campaignBId),
            default => null,
        };
        return [
            'campaignAMetrics' => $metricsA,
            'campaignBMetrics' => $metricsB,
            'winner' => $winner,
        ];
    }

    /**
     * @param $campaigns
     * @return array
     */
    private function aggregateMetrics($campaigns): array
    {
        $aggregate = [
            'total_sent' => 0,
            'delivered' => 0,
            'opens' => 0,
            'unique_opens' => 0,
            'clicks' => 0,
            'unique_clicks' => 0,
            'unsubscribes' => 0,
            'bounces' => 0,
            'conversions' => 0,
        ];

        foreach ($campaigns as $campaign) {
            $metrics = $this->calculateMetrics($campaign);

            $aggregate['total_sent'] += $metrics['total_sent'];
            $aggregate['delivered'] += $metrics['delivered'];
            $aggregate['opens'] += $metrics['opens'];
            $aggregate['unique_opens'] += $metrics['unique_opens'];
            $aggregate['clicks'] += $metrics['clicks'];
            $aggregate['unique_clicks'] += $metrics['unique_clicks'];
            $aggregate['unsubscribes'] += $metrics['unsubscribes'];
            $aggregate['bounces'] += $metrics['bounces'];
            $aggregate['conversions'] += $metrics['conversions'];
        }

        return $this->additionalRateCounting($aggregate);
    }

    /**
     * @param int $campaignId
     * @return array
     */
    public function getSegmentedMetrics(int $campaignId): array
    {
        $emailLogs = EmailLog::where('campaign_id', $campaignId)->get();

        $segmentedMetrics = [];

        foreach ($emailLogs as $log) {
            $tags = json_decode($log->tags);

            if ($tags) {
                foreach ($tags as $tag) {
                    if (!isset($segmentedMetrics[$tag])) {
                        $segmentedMetrics[$tag] = [
                            'total_sent' => 0,
                            'delivered' => 0,
                            'opens' => 0,
                            'clicks' => 0,
                            'unsubscribes' => 0,
                            'bounces' => 0,
                        ];
                    }

                    $segmentedMetrics[$tag]['total_sent']++;

                    switch ($log->status) {
                        case 'delivered':
                            $segmentedMetrics[$tag]['delivered']++;
                            break;
                        case 'opened':
                            $segmentedMetrics[$tag]['opens']++;
                            break;
                        case 'clicked':
                            $segmentedMetrics[$tag]['clicks']++;
                            break;
                        case 'unsubscribed':
                            $segmentedMetrics[$tag]['unsubscribes']++;
                            break;
                        case 'bounced':
                            $segmentedMetrics[$tag]['bounces']++;
                            break;
                    }
                }
            }
        }

        return $segmentedMetrics;
    }

    /**
     * @param array $metricsA
     * @param array $metricsB
     * @return string
     */
    private function compareABMetrics(array $metricsA, array $metricsB): string
    {
        // Если нет кликов, но есть открытия, учитываем открытия
        if ((int)$metricsA['click_rate'] === 0 && (int)$metricsB['click_rate'] === 0) {
            if ($metricsA['open_rate'] > $metricsB['open_rate']) {
                return self::TEST_A;
            } elseif ($metricsB['open_rate'] > $metricsA['open_rate']) {
                return self::TEST_B;
            } else {
                return self::TIE;
            }
        }

        // Если есть клики, используем их для сравнения
        $comparisonMetrics = ['click_rate', 'conversion_rate'];
        $scoreA = 0;
        $scoreB = 0;

        foreach ($comparisonMetrics as $metric) {
            if ($metricsA[$metric] > $metricsB[$metric]) {
                $scoreA++;
            } elseif ($metricsB[$metric] > $metricsA[$metric]) {
                $scoreB++;
            }
        }

        if ($scoreA > $scoreB) {
            return self::TEST_A;
        } elseif ($scoreB > $scoreA) {
            return self::TEST_B;
        } else {
            return self::TIE;
        }
    }


    /**
     * @param int $part
     * @param int $total
     * @return float
     */
    private function calculateRate(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0.0;
    }

    /**
     * @param array $metrics
     * @return array
     */
    private function additionalRateCounting(array $metrics): array
    {
        $metrics['open_rate'] = $this->calculateRate($metrics['unique_opens'], $metrics['delivered']);
        $metrics['click_rate'] = $this->calculateRate($metrics['unique_clicks'], $metrics['delivered']);
        $metrics['bounce_rate'] = $this->calculateRate($metrics['bounces'], $metrics['total_sent']);
        $metrics['soft_bounce_rate'] = $this->calculateRate($metrics['soft_bounces'], $metrics['total_sent']);
        $metrics['hard_bounce_rate'] = $this->calculateRate($metrics['hard_bounces'], $metrics['total_sent']);
        $metrics['unsubscribe_rate'] = $this->calculateRate($metrics['unsubscribes'], $metrics['delivered']);
        $metrics['conversion_rate'] = $this->calculateRate($metrics['conversions'], $metrics['unique_clicks']);

        return $metrics;
    }

    /**
     * @param int $campaignId
     * @return array
     */
    public function getBounceAnalysis(int $campaignId): array
    {
        $emailLogs = EmailLog::where('campaign_id', $campaignId)
            ->whereIn('status', ['bounced', 'soft_bounced', 'hard_bounced'])
            ->get();

        $softBounces = $emailLogs->where('status', 'soft_bounced')->count();
        $hardBounces = $emailLogs->where('status', 'hard_bounced')->count();
        $totalBounces = $emailLogs->count();

        // Top-5 причин отказов
        $topBounceReasons = $emailLogs->pluck('bounce_reason')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->all();

        $metrics = $this->calculateMetrics(Campaign::findOrFail($campaignId));

        return [
            'total_bounces' => $totalBounces,
            'soft_bounces' => $softBounces,
            'hard_bounces' => $hardBounces,
            'soft_bounce_rate' => $this->calculateRate($softBounces, $metrics['total_sent']),
            'hard_bounce_rate' => $this->calculateRate($hardBounces, $metrics['total_sent']),
            'bounce_reasons' => array_keys($topBounceReasons),
            'bounce_reason_counts' => $topBounceReasons,
        ];
    }

    /**
     * @param int $campaignId
     * @return array|array[]
     * @throws \Exception
     */
    public function calculateTimeMetrics(int $campaignId): array
    {
        $emailLogs = EmailLog::where('campaign_id', $campaignId)->get();

        if (!$emailLogs) {
            throw new \Exception('Email logs not found');
        }

        // Временные метрики: дни недели, часы, среднее время до первого открытия/клика
        $timeMetrics = [
            'open_times' => [],
            'click_times' => [],
        ];

        foreach ($emailLogs as $log) {
            if ($log->status === 'opened') {
                $timeMetrics['opened_at'][] = $log->created_at->format('H');
            } elseif ($log->status === 'clicked') {
                $timeMetrics['clicked_at'][] = $log->created_at->format('H');
            }
        }

        // Рассчёт среднего времени до первого открытия и клика
        $timeMetrics['avg_time_to_open'] = $this->calculateAvgTimeToEvent($emailLogs, 'opened');
        $timeMetrics['avg_time_to_click'] = $this->calculateAvgTimeToEvent($emailLogs, 'clicked');

        return $timeMetrics;
    }

    /**
     * @param $emailLogs
     * @param $eventType
     * @return float
     */
    private function calculateAvgTimeToEvent($emailLogs, $eventType): float
    {
        $times = $emailLogs->where('status', $eventType)->pluck('created_at')
            ->map(fn($timestamp) => $timestamp->diffInSeconds($emailLogs->first()->created_at));

        return abs($times->avg()) / 60;  // Конвертация в минуты
    }

    /**
     * @param int $campaignId
     * @param string|null $tag
     * @param string|null $bounceType
     * @param string|null $timePeriod
     * @return array
     */
    public function getFilteredCampaignMetrics(
        int     $campaignId,
        ?string $tag = null,
        ?string $bounceType = null,
        ?string $timePeriod = null
    ): array
    {
        $query = EmailLog::where('campaign_id', $campaignId);

        if ($tag) {
            $query->whereJsonContains('tags', $tag);
        }

        if ($bounceType) {
            $query->where('status', $bounceType === 'soft' ? 'soft_bounced' : 'hard_bounced');
        }

        if ($timePeriod) {
            switch ($timePeriod) {
                case 'morning':
                    $query->whereTime('created_at', '>=', '06:00:00')
                        ->whereTime('created_at', '<', '12:00:00');
                    break;
                case 'afternoon':
                    $query->whereTime('created_at', '>=', '12:00:00')
                        ->whereTime('created_at', '<', '18:00:00');
                    break;
                case 'evening':
                    $query->whereTime('created_at', '>=', '18:00:00')
                        ->whereTime('created_at', '<', '24:00:00');
                    break;
                case 'night':
                    $query->whereTime('created_at', '>=', '00:00:00')
                        ->whereTime('created_at', '<', '06:00:00');
                    break;
            }
        }

        $filteredLogs = $query->get();
        return $this->calculateMetricsFromLogs($filteredLogs);
    }

    /**
     * @param Campaign $campaign
     * @return array
     */
    private function calculateMetrics(Campaign $campaign): array
    {
        $emailLogs = $campaign->emailLogs;
        return $this->calculateMetricsFromLogs($emailLogs);
    }

    /**
     * @param $logs
     * @return array
     */
    private function calculateMetricsFromLogs($logs): array
    {
        $metrics = [
            'total_sent' => $logs->count(),
            'delivered' => $logs->where('status', 'delivered')->count(),
            'opens' => $logs->where('status', 'opened')->count(),
            'unique_opens' => $logs->where('status', 'opened')->unique('email')->count(),
            'clicks' => $logs->where('status', 'clicked')->count(),
            'unique_clicks' => $logs->where('status', 'clicked')->unique('email')->count(),
            'unsubscribes' => $logs->where('status', 'unsubscribed')->count(),
            'bounces' => $logs->where('status', 'bounced')->count(),
            'soft_bounces' => $logs->where('status', 'soft_bounced')->count(),
            'hard_bounces' => $logs->where('status', 'hard_bounced')->count(),
            'conversions' => $logs->where('status', 'converted')->count(),
        ];

        return $this->additionalRateCounting($metrics);
    }


}
