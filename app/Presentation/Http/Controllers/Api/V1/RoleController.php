<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    private const BASE_ASSIGNABLE_ROLES = [
        'user',
        'viewer',
        'support',
    ];

    private const ELEVATED_ASSIGNABLE_ROLES = [
        'admin',
        'tenant_owner',
    ];

    /**
     * Assignable roles
     *
     * Return roles that the authenticated user can assign when creating users.
     *
     * @group Roles
     *
     * @authenticated
     *
     * @response 200 {"data":[{"slug":"user","name":"User","description":"Standard user access"}]}
     */
    public function assignable(Request $request): JsonResponse
    {
        $user = $request->user();
        $slugs = self::BASE_ASSIGNABLE_ROLES;

        if ($user instanceof UserModel && ($user->hasRole('tenant_owner') || $user->hasRole('super_admin'))) {
            $slugs = array_merge($slugs, self::ELEVATED_ASSIGNABLE_ROLES);
        }

        $roles = RoleModel::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->sortBy(fn (RoleModel $role) => array_search($role->slug, $slugs, true))
            ->values()
            ->map(fn (RoleModel $role) => [
                'slug' => $role->slug,
                'name' => $role->name,
                'description' => $role->description,
            ])
            ->all();

        return ApiResponse::ok($roles);
    }
}
