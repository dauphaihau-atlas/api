<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserImportTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_import_template_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/users/import/template');

        $response->assertStatus(401);
    }

    public function test_download_import_template_returns_403_when_authenticated_as_non_admin(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->get('/api/v1/users/import/template', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_download_import_template_returns_csv_for_admin(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->get('/api/v1/users/import/template', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'users-import-template.csv',
            $response->headers->get('Content-Disposition') ?? ''
        );
    }

    public function test_download_import_template_csv_has_correct_headers(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->get('/api/v1/users/import/template', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $firstLine = strtok($content, "\n");

        $this->assertSame('name,email,role', trim((string) $firstLine));
    }
}
