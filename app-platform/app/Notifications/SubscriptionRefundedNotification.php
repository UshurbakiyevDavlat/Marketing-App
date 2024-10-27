<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRefundedNotification extends Notification
{
    use Queueable;

    protected Subscription $subscription;
    protected float $amount;

    public function __construct(Subscription $subscription, float $amount)
    {
        $this->subscription = $subscription;
        $this->amount = $amount;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Refund Processed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A refund of $' . number_format($this->amount, 2) . ' has been processed for your ' . $this->subscription->plan->name . ' plan.')
            ->line('Thank you for your patience during this process.')
            ->action('View Subscription', url('/user/subscriptions'))
            ->line('Thank you for using our application!');
    }
}
