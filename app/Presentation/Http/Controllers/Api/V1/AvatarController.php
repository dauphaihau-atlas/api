<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\User\UpdateUserAvatar\UpdateUserAvatarUseCase;
use App\Exceptions\ServiceUnavailableException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\UploadAvatarRequest;
use App\Presentation\Http\Resources\UserResource;
use App\Presentation\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AvatarController extends Controller
{
    public function __construct(
        private readonly UpdateUserAvatarUseCase $updateUserAvatarUseCase,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Upload my avatar
     *
     * Upload or replace the authenticated user's avatar.
     *
     * @group Avatars
     *
     * @authenticated
     *
     * @response 200 {"id":1,"name":"Admin","email":"admin@example.com","avatar_url":"http://localhost/storage/avatars/abc123.jpg","created_at":"2025-01-01 00:00:00"}
     * @response 422 {"message":"Validation failed","errors":{"avatar":["The avatar field is required."]}}
     */
    public function updateMe(UploadAvatarRequest $request): JsonResponse
    {
        // Get the authenticated user's primary key (id) via Laravel's getAuthIdentifier(); null if unauthenticated.
        $authId = $request->user()?->getAuthIdentifier();
        if ($authId === null) {
            throw new UnauthorizedException('Unauthenticated');
        }

        return $this->uploadAvatarForUser((int) $authId, $request);
    }

    /**
     * Upload user avatar
     *
     * Upload or replace a specific user's avatar. Requires admin role.
     *
     * @group Avatars
     *
     * @authenticated
     *
     * @urlParam user integer required The user ID. Example: 1
     *
     * @response 200 {"id":1,"name":"Admin","email":"admin@example.com","avatar_url":"http://localhost/storage/avatars/abc123.jpg","created_at":"2025-01-01 00:00:00"}
     * @response 422 {"message":"Validation failed","errors":{"avatar":["The avatar field is required."]}}
     */
    public function updateUser(UploadAvatarRequest $request, UserModel $user): JsonResponse
    {
        return $this->uploadAvatarForUser((int) $user->getKey(), $request);
    }

    private function uploadAvatarForUser(int $userId, UploadAvatarRequest $request): JsonResponse
    {
        $file = $request->file('avatar');
        if ($file === null || ! $file->isValid()) {
            throw new ValidationException('Valid avatar file is required', 'AVATAR_FILE_REQUIRED');
        }

        $diskName = config('filesystems.avatars_disk', 'public');
        $storage = Storage::disk($diskName);

        $existingUser = $this->userRepository->findById($userId);
        $oldPath = $existingUser?->getAvatarPath();
        if ($oldPath !== null && $oldPath !== '') {
            try {
                if ($storage->exists($oldPath)) {
                    $storage->delete($oldPath);
                }
            } catch (Throwable $e) {
                Log::warning('Failed to delete previous avatar file', [
                    'path' => $oldPath,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $path = $storage->putFile('avatars', $file);
        } catch (Throwable $e) {
            Log::error('Avatar storage write failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw new ServiceUnavailableException(
                'Failed to store avatar',
                'AVATAR_STORE_FAILED',
                config('app.debug') ? ['error' => $e->getMessage()] : null,
                0,
                $e
            );
        }

        if ($path === false || $path === '') {
            throw new ServiceUnavailableException('Failed to store avatar', 'AVATAR_STORE_FAILED');
        }

        $user = $this->updateUserAvatarUseCase->execute($userId, $path);

        return ApiResponse::ok(new UserResource($user));
    }
}
