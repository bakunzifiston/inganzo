<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base notification: e-mail to everyone, plus an in-app (database) entry for registered users.
 */
abstract class MarketplaceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function subject(): string;

    abstract protected function line(): string;

    abstract protected function actionUrl(object $notifiable): string;

    protected function actionText(): string
    {
        return __('View details');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->greeting(__('Hello!'))
            ->line($this->line())
            ->action($this->actionText(), $this->actionUrl($notifiable));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->subject(),
            'body' => $this->line(),
            'url' => $this->actionUrl($notifiable),
        ];
    }
}
