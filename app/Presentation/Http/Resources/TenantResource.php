<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources;

use App\Core\Domain\Entities\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;

        return [
            'id' => $tenant->getId(),
            'name' => $tenant->getName(),
            'slug' => $tenant->getSlug(),
            'settings' => $tenant->getSettings(),
            'is_active' => $tenant->isActive(),
            'version' => $tenant->getVersion(),
            'created_at' => $tenant->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $tenant->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
