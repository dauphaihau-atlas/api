<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Observers;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserModelObserver
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    private const LOG_NAME = 'default';

    /**
     * Attributes that must never be stored in activity log properties.
     */
    private const SENSITIVE_ATTRIBUTES = ['password', 'remember_token'];

    public function created(UserModel $model): void
    {
        Cache::tags(['users'])->flush();
        Cache::increment('version:users');
        $this->log('created', $model, oldValues: null, newValues: $this->safeSnapshot($model));
    }

    public function updated(UserModel $model): void
    {
        $changes = $this->getSafeChanges($model);
        if ($changes === []) {
            return;
        }
        Cache::increment('version:users');
        $this->log('updated', $model, oldValues: $changes['old'], newValues: $changes['new']);
    }

    public function deleted(UserModel $model): void
    {
        Cache::tags(['users'])->flush();
        Cache::increment('version:users');
        $this->log('deleted', $model, oldValues: $this->safeSnapshot($model), newValues: null);
    }

    public function restored(UserModel $model): void
    {
        Cache::tags(['users'])->flush();
        Cache::increment('version:users');
        $this->log('restored', $model, oldValues: null, newValues: $this->safeSnapshot($model));
    }

    public function forceDeleted(UserModel $model): void
    {
        Cache::tags(['users'])->flush();
        Cache::increment('version:users');
        $this->log('force_deleted', $model, oldValues: $this->safeSnapshot($model), newValues: null);
    }

    private function log(string $event, UserModel $model, ?array $oldValues, ?array $newValues): void
    {
        $causer = Auth::user();

        ActivityLogModel::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'log_name' => self::LOG_NAME,
            'event' => $event,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'causer_type' => $causer !== null ? $causer->getMorphClass() : null,
            'causer_id' => $causer?->getAuthIdentifier(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
        Cache::increment('version:activity-logs');
    }

    /**
     * Safe snapshot: only non-sensitive attributes.
     *
     * @return array<string, mixed>
     */
    private function safeSnapshot(UserModel $model): array
    {
        $attrs = [];
        foreach (['name', 'email', 'avatar_path'] as $key) {
            if (array_key_exists($key, $model->getAttributes())) {
                $attrs[$key] = $model->getAttribute($key);
            }
        }

        return $attrs;
    }

    /**
     * For updated: old and new values for changed attributes only, excluding sensitive ones.
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}|array{}
     */
    private function getSafeChanges(UserModel $model): array
    {
        $changes = $model->getChanges();
        $filtered = $this->filterSensitive($changes);
        if ($filtered === []) {
            return [];
        }

        $old = [];
        $new = [];
        foreach (array_keys($filtered) as $key) {
            $old[$key] = $model->getOriginal($key);
            $new[$key] = $model->getAttribute($key);
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function filterSensitive(array $attrs): array
    {
        foreach (self::SENSITIVE_ATTRIBUTES as $sensitive) {
            unset($attrs[$sensitive]);
        }
        unset($attrs['updated_at']);

        return $attrs;
    }
}
