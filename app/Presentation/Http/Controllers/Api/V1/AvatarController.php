<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\User\UpdateUserAvatar\UpdateUserAvatarUseCase;
use App\Exceptions\ServiceUnavailableException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\UploadAvatarRequest;
use App\Presentation\Http\Resources\UserResource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    public function __construct(
        private readonly UpdateUserAvatarUseCase $updateUserAvatarUseCase,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Upload or replace the authenticated user's avatar.
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
     * Upload or replace a user's avatar (admin only).
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
            } catch (\Throwable $e) {
                Log::warning('Failed to delete previous avatar file', [
                    'path' => $oldPath,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $path = $storage->putFile('avatars', $file);
        } catch (\Throwable $e) {
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

        return response()->json(new UserResource($user), 200);
    }
}
