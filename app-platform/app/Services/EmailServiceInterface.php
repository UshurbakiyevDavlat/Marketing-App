<?php

namespace App\Services;

use App\Models\Campaign;

interface EmailServiceInterface
{
    /**
     * Отправка кампании.
     *
     * @param array $recipients
     * @param string $subject
     * @param string $content
     * @param Campaign $campaign
     * @param string $senderEmail
     * @param string $senderName
     * @return void
     */
    public function sendCampaign(
        array    $recipients,
        string   $subject,
        string   $content,
        Campaign $campaign,
        string   $senderEmail,
        string   $senderName
    ): void;
}
