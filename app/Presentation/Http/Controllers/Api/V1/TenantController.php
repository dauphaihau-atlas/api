<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\UseCases\Tenant\CreateTenant\CreateTenantRequest as CreateTenantUseCaseRequest;
use App\Core\Application\UseCases\Tenant\CreateTenant\CreateTenantUseCase;
use App\Core\Application\UseCases\Tenant\DeleteTenant\DeleteTenantRequest;
use App\Core\Application\UseCases\Tenant\DeleteTenant\DeleteTenantUseCase;
use App\Core\Application\UseCases\Tenant\GetTenant\GetTenantRequest;
use App\Core\Application\UseCases\Tenant\GetTenant\GetTenantUseCase;
use App\Core\Application\UseCases\Tenant\ListTenants\ListTenantsRequest;
use App\Core\Application\UseCases\Tenant\ListTenants\ListTenantsUseCase;
use App\Core\Application\UseCases\Tenant\UpdateTenant\UpdateTenantRequest as UpdateTenantUseCaseRequest;
use App\Core\Application\UseCases\Tenant\UpdateTenant\UpdateTenantUseCase;
use App\Core\Domain\Entities\Tenant;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\CreateTenantRequest;
use App\Presentation\Http\Requests\UpdateTenantRequest;
use App\Presentation\Http\Resources\TenantResource;
use App\Presentation\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly ListTenantsUseCase $listTenantsUseCase,
        private readonly CreateTenantUseCase $createTenantUseCase,
        private readonly GetTenantUseCase $getTenantUseCase,
        private readonly UpdateTenantUseCase $updateTenantUseCase,
        private readonly DeleteTenantUseCase $deleteTenantUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $response = $this->listTenantsUseCase->execute(new ListTenantsRequest(
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', 15),
        ));

        return ApiResponse::ok(
            array_map(fn (Tenant $tenant) => new TenantResource($tenant), $response->tenants),
            meta: [
                'total' => $response->total,
                'per_page' => $response->perPage,
                'current_page' => $response->currentPage,
            ]
        );
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $response = $this->createTenantUseCase->execute(new CreateTenantUseCaseRequest(
            name: $validated['name'],
            slug: $validated['slug'],
            settings: $validated['settings'] ?? null,
            isActive: $validated['is_active'] ?? true,
        ));

        $tenant = new Tenant(
            id: $response->id,
            name: $response->name,
            slug: $response->slug,
            settings: $response->settings,
            isActive: $response->isActive,
            createdAt: $response->createdAt,
        );

        return ApiResponse::created(new TenantResource($tenant));
    }

    public function show(int $id): JsonResponse
    {
        $response = $this->getTenantUseCase->execute(new GetTenantRequest(id: $id));

        $tenant = new Tenant(
            id: $response->id,
            name: $response->name,
            slug: $response->slug,
            settings: $response->settings,
            isActive: $response->isActive,
            createdAt: $response->createdAt,
            updatedAt: $response->updatedAt,
        );

        return ApiResponse::ok(new TenantResource($tenant));
    }

    public function update(UpdateTenantRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $response = $this->updateTenantUseCase->execute(new UpdateTenantUseCaseRequest(
            id: $id,
            version: (int) $validated['version'],
            name: $validated['name'] ?? null,
            slug: $validated['slug'] ?? null,
            settings: $validated['settings'] ?? null,
            isActive: isset($validated['is_active']) ? (bool) $validated['is_active'] : null,
            settingsProvided: array_key_exists('settings', $validated),
        ));

        $tenant = new Tenant(
            id: $response->id,
            name: $response->name,
            slug: $response->slug,
            settings: $response->settings,
            isActive: $response->isActive,
            version: $response->version,
        );

        return ApiResponse::ok(new TenantResource($tenant));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->deleteTenantUseCase->execute(new DeleteTenantRequest(id: $id));

        return ApiResponse::noContent();
    }
}
