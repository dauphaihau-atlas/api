<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PermissionModel extends Model
{
    protected $table = 'permissions';

    protected $fillable = ['name', 'slug', 'group'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleModel::class, 'permission_role', 'permission_id', 'role_id');
    }
}
