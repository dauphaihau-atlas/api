<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLogModel extends Model
{
    protected $table = 'activity_log';

    protected $fillable = [
        'log_name',
        'event',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * Subject of the activity (e.g. the UserModel that was created/updated/deleted).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * User or model that caused the activity (nullable for queue/CLI).
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }
}
