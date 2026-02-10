<?php

namespace App\Infrastructure\External\Email;

use App\Core\Application\Services\EmailServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Log-only email implementation for development. Replace with a real provider (e.g. SendGrid, Mailgun) in production.
 */
class LogEmailService implements EmailServiceInterface
{
    public function send(string $to, string $subject, string $body): bool
    {
        if ($to === '' || $subject === '') {
            Log::warning('LogEmailService: empty to or subject', ['to' => $to, 'subject' => $subject]);

            return false;
        }

        Log::info('Email (log)', [
            'to' => $to,
            'subject' => $subject,
            'body_preview' => strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body,
        ]);

        return true;
    }
}
