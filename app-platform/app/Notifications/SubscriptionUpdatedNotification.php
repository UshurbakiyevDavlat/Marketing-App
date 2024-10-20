<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpdatedNotification extends Notification
{
    use Queueable;

    protected string $newPlan;
    protected string $oldPlan;
    protected string $status;

    /**
     * Create a new notification instance.
     *
     * @param string $newPlan
     * @param string $oldPlan
     * @param string $status
     */
    public function __construct(string $newPlan, string $oldPlan, string $status)
    {
        $this->newPlan = $newPlan;
        $this->oldPlan = $oldPlan;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via(mixed $notifiable): array
    {
        return ['sendgrid'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your Subscription Plan has been Updated')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We wanted to let you know that your subscription has been updated.');

        switch ($this->status) {
            case 'upgraded':
                $message->line("Congratulations! You've been upgraded from the **{$this->oldPlan}** plan to the **{$this->newPlan}** plan.")
                    ->line('You now have access to additional features and higher limits.');
                break;
            case 'downgraded':
                $message->line("Your subscription has been downgraded from the **{$this->oldPlan}** plan to the **{$this->newPlan}** plan.")
                    ->line('You may have fewer features or lower limits. Please review your plan details.');
                break;
            case 'renewed':
                $message->line("Your **{$this->newPlan}** subscription plan has been successfully renewed.")
                    ->line('Thank you for continuing to use our service.');
                break;
            case 'canceled':
                $message->line("Your **{$this->oldPlan}** subscription plan has been canceled.")
                    ->line('If you have any questions or wish to reactivate your subscription, please contact our support team.');
                break;
        }

        return $message->action('View Subscription', url('/subscriptions'))
            ->line('Thank you for choosing our service.');
    }
}
