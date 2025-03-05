<?php

namespace App\Services;

use App\Models\Campaign;

interface EmailServiceInterface
{
    /**
     * Отправка кампании.
     *
     * @param array $recipients
     * @param Campaign $campaign
     * @param string $senderEmail
     * @param string $senderName
     * @return void
     */
    public function sendCampaign(
        array    $recipients,
        Campaign $campaign,
        string   $senderEmail,
        string   $senderName
    ): void;
}
