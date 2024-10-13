<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\User;
use Exception;

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
            $campaign->subject,
            $campaign->content,
            $campaign,
            $user->email,
            $user->name,
        );
    }
}
