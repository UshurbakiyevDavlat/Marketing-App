<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Database\RecordNotFoundException;

class CampaignAnalyticsService
{
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
            'A' => Campaign::findOrFail($campaignAId),
            'B' => Campaign::findOrFail($campaignBId),
            default => null,
        };
        return [
            'campaignAMetrics' => $metricsA,
            'campaignBMetrics' => $metricsB,
            'winner' => $winner,
        ];
    }

    /**
     * @param Campaign $campaign
     * @return array
     */
    private function calculateMetrics(Campaign $campaign): array
    {
        $metrics = [
            'total_sent' => $campaign->emailLogs()->count(),
            'delivered' => $campaign->emailLogs()->where('status', 'delivered')->count(),
            'opens' => $campaign->emailLogs()->where('status', 'opened')->count(),
            'unique_opens' => $campaign->emailLogs()->where('status', 'opened')->distinct('email')->count(),
            'clicks' => $campaign->emailLogs()->where('status', 'clicked')->count(),
            'unique_clicks' => $campaign->emailLogs()->where('status', 'clicked')->distinct('email')->count(),
            'unsubscribes' => $campaign->emailLogs()->where('status', 'unsubscribed')->count(),
            'bounces' => $campaign->emailLogs()->where('status', 'bounced')->count(),
            'conversions' => $campaign->emailLogs()->where('status', 'converted')->count(),
        ];

        return $this->additionalRateCounting($metrics);
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
     * @param array $metricsA
     * @param array $metricsB
     * @return string
     */
    private function compareABMetrics(array $metricsA, array $metricsB): string
    {
        // Если нет кликов, но есть открытия, учитываем открытия
        if ((int)$metricsA['click_rate'] === 0 && (int)$metricsB['click_rate'] === 0) {
            if ($metricsA['open_rate'] > $metricsB['open_rate']) {
                return 'A';
            } elseif ($metricsB['open_rate'] > $metricsA['open_rate']) {
                return 'B';
            } else {
                return 'Tie';
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
            return 'A';
        } elseif ($scoreB > $scoreA) {
            return 'B';
        } else {
            return 'Tie';
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
        $metrics['unsubscribe_rate'] = $this->calculateRate($metrics['unsubscribes'], $metrics['delivered']);
        $metrics['conversion_rate'] = $this->calculateRate($metrics['conversions'], $metrics['unique_clicks']);

        return $metrics;
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
}
