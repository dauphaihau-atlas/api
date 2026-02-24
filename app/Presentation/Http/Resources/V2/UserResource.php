<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\V2;

use App\Core\Application\UseCases\User\CreateUser\CreateUserResponse;
use App\Core\Domain\Entities\Role;
use App\Core\Domain\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * V2 user representation.
 *
 * Changes from V1:
 *   - avatar_url (string|null)  →  avatar: { url: string|null }
 *   - roles: string[]           →  roles: { slug: string, name: string }[]
 *   - created_at format         →  ISO-8601 (was "Y-m-d H:i:s")
 *   - updated_at field added
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof User) {
            $avatarPath = $this->resource->getAvatarPath();
            $avatarUrl = null;
            if ($avatarPath !== null && $avatarPath !== '') {
                try {
                    $avatarUrl = Storage::disk(config('filesystems.avatars_disk', 'public'))->url($avatarPath);
                } catch (Throwable) {
                    $avatarUrl = null;
                }
            }

            $data = [
                'id' => $this->resource->getId(),
                'name' => $this->resource->getName(),
                'email' => $this->resource->getEmail()->getValue(),
                'avatar' => ['url' => $avatarUrl],
                'roles' => array_map(
                    fn (Role $r) => ['slug' => $r->getSlug(), 'name' => $r->getName()],
                    $this->resource->getRoles(),
                ),
                'created_at' => $this->resource->getCreatedAt()?->format('c'),
                'updated_at' => $this->resource->getUpdatedAt()?->format('c'),
            ];

            if ($this->resource->getDeletedAt() !== null) {
                $data['deleted_at'] = $this->resource->getDeletedAt()->format('c');
            }

            return $data;
        }

        if ($this->resource instanceof CreateUserResponse) {
            return [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'avatar' => ['url' => null],
                'roles' => [],
                'created_at' => $this->resource->createdAt->format('c'),
                'updated_at' => $this->resource->createdAt->format('c'),
            ];
        }

        return [];
    }
}
