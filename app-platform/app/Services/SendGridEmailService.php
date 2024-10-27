<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\EmailLog;
use SendGrid;
use SendGrid\Mail\Mail;
use Exception;
use Illuminate\Support\Facades\Log;
use SendGrid\Mail\TypeException;

class SendGridEmailService implements EmailServiceInterface
{
    protected SendGrid $sendGrid;

    public function __construct()
    {
        $this->sendGrid = new SendGrid(config('services.sendgrid.api_key'));
    }

    /**
     * Отправка кампании через SendGrid.
     *
     * @param array $recipients
     * @param Campaign $campaign
     * @param string $senderEmail
     * @param string $senderName
     * @return void
     * @throws TypeException
     */
    public function sendCampaign(
        array    $recipients,
        Campaign $campaign,
        string   $senderEmail,
        string   $senderName,
    ): void
    {

        foreach ($recipients as $subscriber) {
            $email = new Mail();
            $email->setFrom($senderEmail, $senderName);
            $email->setSubject($campaign->subject);
            $email->addTo($subscriber['email'], $subscriber['name'] ?? 'Dear Subscriber');
            $email->addContent("text/plain", $campaign->content);
            $email->addContent("text/html", "<strong>" . $campaign->content . "</strong>");

            $email->addCustomArg('campaign_id', "$campaign->id");

            try {
                $response = $this->sendGrid->send($email);

                if ($response->statusCode() != 202) {
                    Log::error('Sendgrid response error-log', ['response' => $response->body()]);
                    continue;
                }

                Log::info('Sendgrid response log', [
                    'subscriber' => $subscriber['email'],
                    'response' => $response->body()
                ]);

                EmailLog::updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'email' => $subscriber['email']
                    ],
                    [
                        'status' => 'delivered',
                        'event' => 'Email sent successfully'
                    ]);
            } catch (Exception $e) {
                Log::error('Sendgrid exception', [
                    'subscriber' => $subscriber['email'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $campaign->update(['status' => 'sent']);
    }
}
