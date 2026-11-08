<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiDocumentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_documentation_is_open_when_ui_enabled_and_basic_auth_disabled(): void
    {
        config([
            'l5-swagger.ui_enabled' => true,
            'l5-swagger.basic_auth.enabled' => false,
        ]);

        $this->get('/api/documentation')->assertOk();
        $this->get('/api/documentation/v1')->assertOk();
        $this->get('/api/documentation/v1/docs/asset/swagger-ui.css')->assertOk();
    }

    public function test_documentation_requires_basic_auth_when_enabled(): void
    {
        config([
            'l5-swagger.ui_enabled' => true,
            'l5-swagger.basic_auth.enabled' => true,
            'l5-swagger.basic_auth.username' => 'docs',
            'l5-swagger.basic_auth.password' => 'secret-pass',
        ]);

        $this->get('/api/documentation')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="API Documentation"');

        $this->get('/api/documentation/v1')->assertUnauthorized();
        $this->get('/api/documentation/v1/docs/asset/swagger-ui-bundle.js')->assertUnauthorized();

        $this->withBasicAuth('docs', 'secret-pass')
            ->get('/api/documentation')
            ->assertOk();

        $this->withBasicAuth('docs', 'secret-pass')
            ->get('/api/documentation/v1')
            ->assertOk();

        $this->withBasicAuth('docs', 'secret-pass')
            ->get('/api/documentation/v1/docs/asset/swagger-ui-bundle.js')
            ->assertOk();
    }

    public function test_documentation_returns_not_found_when_ui_disabled(): void
    {
        config([
            'l5-swagger.ui_enabled' => false,
            'l5-swagger.basic_auth.enabled' => false,
        ]);

        $this->get('/api/documentation')->assertNotFound();
        $this->get('/api/documentation/v1')->assertNotFound();
        $this->get('/api/documentation/v1/docs')->assertNotFound();
        $this->get('/api/documentation/v1/docs/asset/swagger-ui.css')->assertNotFound();
    }

    public function test_basic_auth_fails_closed_when_credentials_missing(): void
    {
        config([
            'l5-swagger.ui_enabled' => true,
            'l5-swagger.basic_auth.enabled' => true,
            'l5-swagger.basic_auth.username' => 'docs',
            'l5-swagger.basic_auth.password' => '',
        ]);

        $this->withBasicAuth('docs', '')
            ->get('/api/documentation')
            ->assertNotFound();
    }

    public function test_configure_l5_swagger_omits_http_routes_when_ui_disabled(): void
    {
        config(['l5-swagger.ui_enabled' => false]);

        app(ApiDocumentationService::class)->configureL5Swagger();

        $this->assertNull(config('l5-swagger.documentations.v1.routes'));
        $this->assertSame('api-docs-v1.json', config('l5-swagger.documentations.v1.paths.docs_json'));
    }

    public function test_configure_l5_swagger_registers_protected_routes_when_ui_enabled(): void
    {
        config(['l5-swagger.ui_enabled' => true]);

        app(ApiDocumentationService::class)->configureL5Swagger();

        $routes = config('l5-swagger.documentations.v1.routes');

        $this->assertIsArray($routes);
        $this->assertSame('api/documentation/v1', $routes['api']);
        $this->assertSame(['docs.protect'], $routes['middleware']['api']);
        $this->assertSame(['docs.protect'], $routes['middleware']['asset']);
    }

    public function test_manager_metadata_works_when_documentation_ui_disabled(): void
    {
        config([
            'l5-swagger.ui_enabled' => false,
            'l5-swagger.basic_auth.enabled' => false,
        ]);

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/User')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.schema_name', 'User');

        $this->assertNotNull(
            $this->actingAs($manager)
                ->getJson('/api/v1/manager/metadata/User')
                ->json('data.swagger_schema')
        );
    }
}
