<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\DTOs\UserFilters;
use App\Core\Domain\Entities\Role;
use App\Core\Domain\Entities\User;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Tenant\TenantContext;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function findById(int $id): ?User
    {
        $model = $this->applyTenantScope(UserModel::with('roles'))->find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $model = $this->applyTenantScope(UserModel::with('roles'))
            ->where('email', $email)
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function save(User $user): User
    {
        if ($user->getId() === null) {
            $model = new UserModel;
            $model->tenant_id = $this->tenantContext->getTenantId();
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
        $model = $this->applyTenantScope(UserModel::query())->find($id);

        return $model !== null && $model->delete();
    }

    public function restore(int $id): bool
    {
        $model = $this->applyTenantScope(UserModel::onlyTrashed())->find($id);

        return $model !== null && $model->restore();
    }

    public function forceDelete(int $id): bool
    {
        $model = $this->applyTenantScope(UserModel::withTrashed())->find($id);

        return $model !== null && $model->forceDelete();
    }

    public function findTrashedById(int $id): ?User
    {
        $model = $this->applyTenantScope(
            UserModel::with('roles')->onlyTrashed(),
        )->find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return $this->applyTenantScope(UserModel::with('roles'))
            ->get()
            ->map(fn (UserModel $model) => $this->toEntity($model))
            ->values()
            ->all();
    }

    /**
     * @return User[]
     */
    public function findAllWithDateRange(
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
    ): array {
        $query = $this->applyTenantScope(UserModel::with('roles'));

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom->format('Y-m-d'));
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo->format('Y-m-d'));
        }

        return $query
            ->get()
            ->map(fn (UserModel $model) => $this->toEntity($model))
            ->values()
            ->all();
    }

    /**
     * @return User[]
     */
    public function findPaginated(
        UserFilters $filters,
        int $page,
        int $perPage,
    ): array {
        $perPage = max(1, min($perPage, 100));
        $offset = max(0, ($page - 1) * $perPage);

        $query = $this->applyFilters(
            $this->applyTenantScope(UserModel::with('roles')),
            $filters,
        );

        $sortColumn =
          $filters->sort !== '' && str_starts_with($filters->sort, '-')
            ? substr($filters->sort, 1)
            : $filters->sort;
        $sortColumn = $this->validateSortColumn($sortColumn);
        $sortDir = str_starts_with($filters->sort, '-') ? 'desc' : 'asc';

        return $query
            ->orderBy($sortColumn, $sortDir)
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (UserModel $model) => $this->toEntity($model))
            ->values()
            ->all();
    }

    public function count(UserFilters $filters): int
    {
        return $this->applyFilters(
            $this->applyTenantScope(UserModel::query()),
            $filters,
        )->count();
    }

    public function countActive(): int
    {
        return $this->applyTenantScope(UserModel::query())->count();
    }

    public function countTrashed(): int
    {
        return $this->applyTenantScope(UserModel::onlyTrashed())->count();
    }

    public function countCreatedToday(): int
    {
        return $this->applyTenantScope(UserModel::query())
            ->whereDate('created_at', today())
            ->count();
    }

    private function applyTenantScope(Builder $query): Builder
    {
        $tenantId = $this->tenantContext->getTenantId();
        if ($tenantId !== null) {
            $query->where('users.tenant_id', $tenantId);
        }

        return $query;
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
                    [$tsquery],
                );
            } else {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like): void {
                    $q->where('name', 'LIKE', $like)->orWhere('email', 'LIKE', $like);
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
        $terms = preg_split("/\s+/", trim($input), -1, PREG_SPLIT_NO_EMPTY);
        if ($terms === false || $terms === []) {
            return '';
        }

        return implode(
            ' & ',
            array_map(function (string $term): string {
                $sanitized = preg_replace("/[^\w@.\-]/", '', $term);

                return $sanitized.':*';
            }, $terms),
        );
    }

    private function validateSortColumn(string $column): string
    {
        $allowed = [
            'id',
            'name',
            'email',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        return in_array($column, $allowed, true) ? $column : 'id';
    }

    public function upsertBatch(array $usersData): array
    {
        $emails = array_column($usersData, 'email');
        $existingCount = $this->applyTenantScope(UserModel::query())
            ->whereIn('email', $emails)
            ->count();

        // Use DB::table() to bypass UserModel's 'hashed' password cast,
        // since passwords are already hashed by the job before calling this method.
        DB::table('users')->upsert(
            $usersData,
            ['email'],
            ['name', 'password', 'updated_at'],
        );

        return [
            'created' => count($usersData) - $existingCount,
            'updated' => $existingCount,
        ];
    }

    private function toEntity(UserModel $model): User
    {
        $roles = $model->relationLoaded('roles')
          ? $model->roles
              ->map(
                  fn (RoleModel $r) => new Role(
                      $r->id,
                      $r->name,
                      $r->slug,
                      $r->description,
                  ),
              )
              ->all()
          : [];

        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            password: $model->password,
            avatarPath: $model->avatar_path,
            roles: $roles,
            tenantId: $model->tenant_id,
            createdAt: $model->created_at?->toDateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable(),
            deletedAt: $model->deleted_at?->toDateTimeImmutable(),
        );
    }
}
