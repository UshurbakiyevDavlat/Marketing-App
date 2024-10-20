<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class CampaignEmailService
{
    protected EmailServiceInterface $emailService;

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
}
