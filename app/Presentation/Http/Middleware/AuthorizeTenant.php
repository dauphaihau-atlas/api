<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeTenant
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        Gate::authorize($ability, TenantModel::class);

        return $next($request);
    }
}
