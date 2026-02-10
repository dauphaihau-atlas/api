<?php

namespace App\Core\Application\Services;

interface EmailServiceInterface
{
    public function send(string $to, string $subject, string $body): bool;
}
