<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Core\Application\Contracts\TenantRepositoryInterface;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantOptional
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $value = $request->header('X-Tenant-ID');

        if ($value !== null && $value !== '') {
            $tenant = is_numeric($value)
                ? $this->tenantRepository->findById((int) $value)
                : $this->tenantRepository->findBySlug($value);

            if ($tenant === null) {
                throw new NotFoundException('Tenant not found.', 'TENANT_NOT_FOUND');
            }

            if (! $tenant->isActive()) {
                throw new ValidationException('Tenant is inactive.', 'TENANT_INACTIVE');
            }

            $this->tenantContext->set($tenant);
        }

        return $next($request);
    }
}
