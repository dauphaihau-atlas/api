<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKeyModel extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'scope_hash',
        'idempotency_key',
        'route_action',
        'request_hash',
        'status',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_status' => 'integer',
            'expires_at' => 'datetime',
            'user_id' => 'integer',
            'tenant_id' => 'integer',
        ];
    }
}
