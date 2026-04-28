<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantModel extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\TenantModelFactory
    {
        return \Database\Factories\TenantModelFactory::new();
    }

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'settings',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(UserModel::class, 'tenant_id');
    }

    public function userImports(): HasMany
    {
        return $this->hasMany(UserImportModel::class, 'tenant_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLogModel::class, 'tenant_id');
    }
}
