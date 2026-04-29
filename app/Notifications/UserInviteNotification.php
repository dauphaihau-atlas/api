<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $userName,
        private readonly string $acceptUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to Atlas')
            ->greeting("Hello {$this->userName}!")
            ->line('An account has been created for you.')
            ->line('Set your password to accept the invite and finish account setup.')
            ->action('Set password', $this->acceptUrl)
            ->line('This invite link is single-use.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'You have been invited to Atlas.',
            'user_name' => $this->userName,
            'type' => 'user_invited',
        ];
    }
}
