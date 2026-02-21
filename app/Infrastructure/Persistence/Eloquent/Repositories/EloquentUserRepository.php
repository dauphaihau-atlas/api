<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\DTOs\UserFilters;
use App\Core\Domain\Entities\Role;
use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $model = UserModel::with('roles')->find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $model = UserModel::with('roles')->where('email', $email)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function save(User $user): User
    {
        if ($user->getId() === null) {
            $model = new UserModel;
        } else {
            $model = UserModel::findOrFail($user->getId());
        }

        $model->name = $user->getName();
        $model->email = $user->getEmail()->getValue();
        if ($user->getPassword() !== null) {
            $model->password = $user->getPassword();
        }
        $model->avatar_path = $user->getAvatarPath();
        $model->save();
        $model->load('roles');

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return UserModel::destroy($id) > 0;
    }

    public function restore(int $id): bool
    {
        $model = UserModel::onlyTrashed()->find($id);

        return $model !== null && $model->restore();
    }

    public function forceDelete(int $id): bool
    {
        $model = UserModel::withTrashed()->find($id);

        return $model !== null && $model->forceDelete();
    }

    public function findTrashedById(int $id): ?User
    {
        $model = UserModel::with('roles')->onlyTrashed()->find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return UserModel::with('roles')->get()
            ->map(fn (UserModel $model) => $this->toEntity($model))
            ->values()
            ->all();
    }

    /**
     * @return User[]
     */
    public function findPaginated(UserFilters $filters, int $page, int $perPage): array
    {
        $perPage = max(1, min($perPage, 100));
        $offset = max(0, ($page - 1) * $perPage);

        $query = $this->applyFilters(UserModel::with('roles'), $filters);

        $sortColumn = $filters->sort !== '' && str_starts_with($filters->sort, '-')
            ? substr($filters->sort, 1)
            : $filters->sort;
        $sortColumn = $this->validateSortColumn($sortColumn);
        $sortDir = str_starts_with($filters->sort, '-') ? 'desc' : 'asc';

        return $query->orderBy($sortColumn, $sortDir)
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (UserModel $model) => $this->toEntity($model))
            ->values()
            ->all();
    }

    public function count(UserFilters $filters): int
    {
        return $this->applyFilters(UserModel::query(), $filters)->count();
    }

    public function countActive(): int
    {
        return UserModel::count();
    }

    public function countTrashed(): int
    {
        return UserModel::onlyTrashed()->count();
    }

    public function countCreatedToday(): int
    {
        return UserModel::whereDate('created_at', today())->count();
    }

    private function applyFilters(Builder $query, UserFilters $filters): Builder
    {
        if ($filters->trashed === 'with') {
            $query->withTrashed();
        } elseif ($filters->trashed === 'only') {
            $query->onlyTrashed();
        }

        if ($filters->search !== null && $filters->search !== '') {
            $search = $filters->search;

            if (DB::connection()->getDriverName() === 'pgsql') {
                $tsquery = $this->buildPrefixTsquery($search);
                $query->whereRaw(
                    "to_tsvector('simple', coalesce(name, '') || ' ' || coalesce(email, '')) @@ to_tsquery('simple', ?)",
                    [$tsquery]
                );
            } else {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like): void {
                    $q->where('name', 'LIKE', $like)
                        ->orWhere('email', 'LIKE', $like);
                });
            }
        }

        return $query;
    }

    /**
     * Build a tsquery string with prefix matching from user input.
     *
     * Splits input into words, sanitizes each, appends :* for prefix matching,
     * and joins with & (AND). E.g. "john doe" becomes "john:* & doe:*".
     */
    private function buildPrefixTsquery(string $input): string
    {
        $terms = preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        if ($terms === false || $terms === []) {
            return '';
        }

        return implode(' & ', array_map(function (string $term): string {
            $sanitized = preg_replace('/[^\w@.\-]/', '', $term);

            return $sanitized.':*';
        }, $terms));
    }

    private function validateSortColumn(string $column): string
    {
        $allowed = ['id', 'name', 'email', 'created_at', 'updated_at', 'deleted_at'];

        return in_array($column, $allowed, true) ? $column : 'id';
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
        $roles = $model->relationLoaded('roles')
            ? $model->roles->map(fn (RoleModel $r) => new Role($r->id, $r->name, $r->slug, $r->description))->all()
            : [];

        return new User(
            id: $model->id,
            name: $model->name,
            email: new Email($model->email),
            password: $model->password,
            avatarPath: $model->avatar_path,
            roles: $roles,
            createdAt: $model->created_at?->toDateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable(),
            deletedAt: $model->deleted_at?->toDateTimeImmutable()
        );
    }
}
