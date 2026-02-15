<?php

namespace App\Presentation\Http\Resources;

use App\Core\Application\UseCases\User\CreateUser\CreateUserResponse;
use App\Core\Domain\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof User) {
            $avatarPath = $this->resource->getAvatarPath();
            $avatarUrl = $avatarPath !== null && $avatarPath !== ''
                ? Storage::disk(config('filesystems.avatars_disk', 'public'))->url($avatarPath)
                : null;

            return [
                'id' => $this->resource->getId(),
                'name' => $this->resource->getName(),
                'email' => $this->resource->getEmail()->getValue(),
                'avatar_url' => $avatarUrl,
                'created_at' => $this->resource->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        if ($this->resource instanceof CreateUserResponse) {
            return [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'avatar_url' => null,
                'created_at' => $this->resource->createdAt->format('Y-m-d H:i:s'),
            ];
        }

        return [];
    }
}
