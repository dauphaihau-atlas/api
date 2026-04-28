<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Core\Application\Contracts\TenantRepositoryInterface;
use App\Core\Domain\Entities\Tenant;
use App\Exceptions\ConflictException;
use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use Illuminate\Support\Facades\DB;

class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function findBySlug(string $slug): ?Tenant
    {
        $model = TenantModel::where('slug', $slug)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findById(int $id): ?Tenant
    {
        $model = TenantModel::find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function save(Tenant $tenant): Tenant
    {
        if ($tenant->getId() === null) {
            $model = new TenantModel;
            $model->name = $tenant->getName();
            $model->slug = $tenant->getSlug();
            $model->settings = $tenant->getSettings();
            $model->is_active = $tenant->isActive();
            $model->save();

            return $this->toEntity($model);
        }

        $affected = TenantModel::where('id', $tenant->getId())
            ->where('version', $tenant->getVersion())
            ->update([
                'name' => $tenant->getName(),
                'slug' => $tenant->getSlug(),
                'settings' => $tenant->getSettings() !== null ? json_encode($tenant->getSettings()) : null,
                'is_active' => $tenant->isActive(),
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new ConflictException(
                'Tenant has been modified by another request. Please refresh and retry.',
                'VERSION_CONFLICT',
            );
        }

        return $this->toEntity(TenantModel::findOrFail($tenant->getId()));
    }

    public function delete(int $id): bool
    {
        return TenantModel::destroy($id) > 0;
    }

    /**
     * @return Tenant[]
     */
    public function findAll(): array
    {
        return TenantModel::all()
            ->map(fn (TenantModel $model) => $this->toEntity($model))
            ->values()
            ->all();
    }

    /**
     * @return Tenant[]
     */
    public function findPaginated(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        return TenantModel::orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (TenantModel $model) => $this->toEntity($model))
            ->values()
            ->all();
    }

    public function count(): int
    {
        return TenantModel::count();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = TenantModel::where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toEntity(TenantModel $model): Tenant
    {
        return new Tenant(
            id: $model->id,
            name: $model->name,
            slug: $model->slug,
            settings: $model->settings,
            isActive: $model->is_active,
            createdAt: $model->created_at?->toDateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable(),
            version: $model->version ?? 1,
        );
    }
}
