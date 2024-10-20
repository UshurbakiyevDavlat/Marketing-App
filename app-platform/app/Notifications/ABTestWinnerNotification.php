<?php

namespace App\Notifications;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ABTestWinnerNotification extends Notification
{
    use Queueable;

    protected Campaign $winnerCampaign;

    /**
     * Create a new notification instance.
     */
    public function __construct(Campaign $winnerCampaign)
    {
        $this->winnerCampaign = $winnerCampaign;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['sendgrid'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A/B Test Results')
            ->line('The winner of your A/B test is campaign "' . $this->winnerCampaign->name . '".')
            ->action('View Results', url('/campaigns/' . $this->winnerCampaign->id))
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
