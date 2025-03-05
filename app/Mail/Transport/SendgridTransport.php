<?php

namespace App\Mail\Transport;

use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use GuzzleHttp\Client;
use Symfony\Component\Mime\RawMessage;

class SendgridTransport implements TransportInterface
{
    protected Client $client;
    protected string $apiKey;

    public function __construct(Client $client, string $apiKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    /**
     * @param Email|RawMessage $message
     * @param Envelope|null $envelope
     * @return SentMessage|null
     * @throws GuzzleException
     */
    public function send(Email|RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        $payload = [
            'personalizations' => [
                [
                    'to' => collect($message->getTo())->map(function ($to) {
                        return ['email' => $to->getAddress(), 'name' => $to->getName()];
                    })->all(),
                    'subject' => $message->getSubject(),
                ],
            ],
            'from' => [
                'email' => $message->getFrom()[0]->getAddress(),
                'name' => $message->getFrom()[0]->getName(),
            ],
            'content' => [
                [
                    'type' => 'text/plain',
                    'value' => $message->getTextBody(),
                ],
                [
                    'type' => 'text/html',
                    'value' => $message->getHtmlBody(),
                ],
            ],
        ];

        $response = $this->client->post('https://api.sendgrid.com/v3/mail/send', [
            'json' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() === 202) {
            // 202 indicates that the email was accepted for delivery
            return new SentMessage($message, $envelope ?? new Envelope($message->getFrom()[0], $message->getTo()));
        }

        // Если отправка не удалась, выбрасываем исключение или возвращаем null.
        throw new TransportException('Failed to send email');
    }

    public function __toString(): string
    {
        return 'sendgrid';
    }
}
