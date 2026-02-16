<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $model = UserModel::find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $model = UserModel::where('email', $email)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function save(User $user): User
    {
        if ($user->getId() === null) {
            $model = new UserModel();
        } else {
            $model = UserModel::findOrFail($user->getId());
        }

        $model->name = $user->getName();
        $model->email = $user->getEmail()->getValue();
        if ($user->getPassword() !== null) {
            $model->password = $user->getPassword();
        }
        $model->avatar_path = $user->getAvatarPath();
        $model->role = $user->getRole() ?? $model->role ?? 'user';
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return UserModel::destroy($id) > 0;
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return UserModel::all()
            ->map(fn (UserModel $model) => $this->toEntity($model))
            ->values()
            ->all();
    }

    public function upsertBatch(array $usersData): array
    {
        $emails = array_column($usersData, 'email');
        $existingCount = UserModel::whereIn('email', $emails)->count();

        // Use DB::table() to bypass UserModel's 'hashed' password cast,
        // since passwords are already hashed by the job before calling this method.
        DB::table('users')->upsert($usersData, ['email'], ['name', 'password', 'updated_at']);

        return [
            'created' => count($usersData) - $existingCount,
            'updated' => $existingCount,
        ];
    }

    private function toEntity(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: new Email($model->email),
            password: $model->password,
            avatarPath: $model->avatar_path,
            role: $model->role ?? null,
            createdAt: $model->created_at?->toDateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable()
        );
    }
}
