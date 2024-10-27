<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCanceledNotification extends Notification
{
    use Queueable;

    protected Subscription $subscription;
    protected ?string $reason;

    public function __construct(Subscription $subscription, ?string $reason = null)
    {
        $this->subscription = $subscription;
        $this->reason = $reason;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Subscription Canceled')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your subscription to the ' . $this->subscription->plan->name . ' plan has been canceled.')
            ->line('Plan: ' . $this->subscription->plan->name);

        if ($this->reason) {
            $mailMessage->line('Cancellation reason: ' . $this->reason);
        }

        return $mailMessage
            ->line('Thank you for using our service. We hope to see you again!')
            ->action('Manage Subscription', url('/user/subscriptions'));
    }
}
