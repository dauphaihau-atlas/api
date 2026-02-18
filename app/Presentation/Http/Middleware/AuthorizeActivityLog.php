<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeActivityLog
{
    /**
     * @param  string  $ability  viewAny|view
     * @param  string|null  $routeParamKey  Route parameter name for id (e.g. 'id') when authorizing 'view'
     */
    public function handle(Request $request, Closure $next, string $ability, ?string $routeParamKey = null): Response
    {
        if ($ability === 'view' && $routeParamKey !== null && $routeParamKey !== '') {

            // e.g For a request to something like /api/v1/activity-logs/42
            // The route might be defined with {id} (or {activity_log} and bound as id).
            // $request->route('id') then returns that segment, e.g. 42 (or "42" as string, depending on the route).
            $id = $request->route($routeParamKey);

            if ($id !== null) {
                $model = ActivityLogModel::find($id);
                if ($model !== null) {
                    Gate::authorize('view', $model);
                }
            } else {
                Gate::authorize($ability, ActivityLogModel::class);
            }
        } else {
            Gate::authorize($ability, ActivityLogModel::class);
        }

        return $next($request);
    }
}
