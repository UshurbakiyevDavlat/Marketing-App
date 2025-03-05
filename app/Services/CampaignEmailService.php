<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\User;
use App\Notifications\ABTestWinnerNotification;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CampaignEmailService
{
    protected EmailServiceInterface $emailService;

    const string RAW_AGGREGATE_METRIC_CONDITION = '
        COUNT(*) as total,
        SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = "opened" THEN 1 ELSE 0 END) as opened,
        SUM(CASE WHEN status = "clicked" THEN 1 ELSE 0 END) as clicked,
        SUM(CASE WHEN status = "unsubscribed" THEN 1 ELSE 0 END) as unsubscribed,
        SUM(CASE WHEN status = "bounced" THEN 1 ELSE 0 END) as bounced
    ';

    public function __construct(EmailServiceInterface $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Отправка кампании через выбранную стратегию.
     *
     * @param Campaign $campaign
     * @param User $user
     * @return void
     * @throws Exception
     */
    public function sendCampaign(Campaign $campaign, User $user): void
    {
        if ($campaign->status === 'sent') {
            throw new Exception('Campaign already sent.');
        }

        if ($campaign->type !== 'email') {
            throw new Exception('Only email campaigns can be sent currently');
        }

        $subscribers = $campaign->subscribers->toArray();

        if (empty($subscribers)) {
            throw new \Exception('No subscribers found');
        }

        $this->emailService->sendCampaign(
            $subscribers,
            $campaign,
            $user->email,
            $user->name,
        );
    }

    /**
     * @param Campaign $campaignA
     * @param Campaign $campaignB
     * @param User $user
     * @return void
     */
    public function sendABTest(Campaign $campaignA, Campaign $campaignB, User $user): void
    {
        $subscribers = $campaignA->subscribers;

        foreach ($subscribers as $index => $subscriber) {
            $campaign = ($index % 2 === 0) ? $campaignA : $campaignB;

            try {
                $this->emailService->sendCampaign([$subscriber], $campaign, $user->email, $user->name);

                EmailLog::updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'email' => $subscriber->email,
                    ],
                    [
                        'status' => 'delivered',
                        'event' => "Email sent to variant " . $campaign->variant,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                Log::info("A/B test email sent", [
                    'subscriber' => $subscriber->email,
                    'campaign_variant' => $campaign->variant
                ]);
            } catch (Exception $e) {
                Log::error("Error sending A/B test email", [
                    'subscriber' => $subscriber->email,
                    'campaign_variant' => $campaign->variant,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('A/B test emails sent successfully');
    }

    /**
     * @param int $campaignAId
     * @param int $campaignBId
     * @return array|JsonResponse|void
     */
    public function determineABTestWinner(int $campaignAId, int $campaignBId)
    {
        $campaignAMetrics = $this->getMetricsForCampaign($campaignAId);
        $campaignBMetrics = $this->getMetricsForCampaign($campaignBId);

        if (!$campaignAMetrics || !$campaignBMetrics) {
            return response()->json(['error' => 'Unable to retrieve metrics for one or both campaigns.'], 404);
        }

        $winner = $this->compareMetrics($campaignAMetrics, $campaignBMetrics);

        if ($winner) {
            switch ($winner) {
                case "A":
                    $winnerObject = Campaign::findOrFail($campaignAId);
                    break;
                case "B":
                    $winnerObject = Campaign::findOrFail($campaignBId);
                    break;
                default:
                    return response()->json(['error' => 'Unable to retrieve metrics for one or both campaigns.'], 404);
            }

            $winnerObject->update(['is_winner' => true]);

            $user = $winnerObject->user;
            $user->notify(new ABTestWinnerNotification($winnerObject));

            return compact('campaignAMetrics', 'campaignBMetrics', 'winner');
        }
    }

    /**
     * Сравнить метрики двух кампаний и определить победителя.
     *
     * @param array $campaignAMetrics
     * @param array $campaignBMetrics
     * @return string
     */
    private function compareMetrics(array $campaignAMetrics, array $campaignBMetrics): string
    {
        //clicks have more priority than opened
        if ($campaignAMetrics['clicked'] > $campaignBMetrics['clicked']) {
            return 'A';
        } elseif ($campaignAMetrics['clicked'] < $campaignBMetrics['clicked']) {
            return 'B';
        }

        //use opened rate only if click rate has draw between campaigns
        if ($campaignAMetrics['opened'] > $campaignBMetrics['opened']) {
            return 'A';
        } elseif ($campaignAMetrics['opened'] < $campaignBMetrics['opened']) {
            return 'B';
        }

        return 'Draw';
    }

    /**
     * Получение метрик для конкретной кампании
     *
     * @param int $campaignId
     * @return array|null
     */
    public function getMetricsForCampaign(int $campaignId): ?array
    {
        return EmailLog::where('campaign_id', $campaignId)
            ->selectRaw(self::RAW_AGGREGATE_METRIC_CONDITION)
            ->first()
            ?->toArray();
    }

    /**
     * Получение метрик для пользователя
     *
     * @param int $userId
     * @return array|null
     */
    public function getMetricsForUser(int $userId): ?array
    {
        return EmailLog::whereHas('campaign', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->selectRaw(self::RAW_AGGREGATE_METRIC_CONDITION)
            ->first()
            ?->toArray();
    }
}
