<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Api\V1\UserController;

return [
    /*
     * How long idempotency records are retained before expiring.
     * Clients may safely retry with the same key within this window.
     */
    'ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),

    /*
     * Route actions that require an Idempotency-Key header.
     * Use the format "ControllerClass@method".
     */
    'required_actions' => [
        UserController::class.'@import',
        UserController::class.'@export',
    ],
];
