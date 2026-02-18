<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources;

use App\Core\Application\DTOs\ActivityLogEntry;
use App\Core\Application\UseCases\ActivityLog\GetActivityLog\GetActivityLogResponse;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof GetActivityLogResponse) {
            $entry = $this->resource->entry;
        } elseif ($this->resource instanceof ActivityLogEntry) {
            $entry = $this->resource;
        } else {
            return [];
        }

        return [
            'id' => $entry->id,
            'log_name' => $entry->logName,
            'event' => $entry->event,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'causer_type' => $entry->causerType,
            'causer_id' => $entry->causerId,
            'causer_name' => $entry->causerName,
            'causer_email' => $entry->causerEmail,
            'properties' => $entry->properties,
            'created_at' => $entry->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
