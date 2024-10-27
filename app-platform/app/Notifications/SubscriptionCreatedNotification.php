<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCreatedNotification extends Notification
{
    use Queueable;

    protected Subscription $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Created Successfully')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your subscription to the ' . $this->subscription->plan->name . ' plan was created successfully.')
            ->line('Subscription details:')
            ->line('Plan: ' . $this->subscription->plan->name)
            ->line('Amount: ' . $this->subscription->plan->price)
            ->line('Thank you for subscribing to our service!')
            ->action('View Subscription', url('/user/subscriptions'))
            ->line('Thank you for using our application!');
    }
}
