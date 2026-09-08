<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Test custom branded 404 error page renders with EventPulse styling.
     */
    public function test_custom_404_error_page_renders_with_brand_styling(): void
    {
        $response = $this->get('/non-existent-event-or-page-route-12345');

        $response->assertStatus(404);
        $response->assertSee('Event or Page Not Found');
        $response->assertSee('Browse All Events');
        $response->assertSee('Return Home');
    }

    /**
     * 2. Test custom branded 403 error page renders on unauthorized access.
     */
    public function test_custom_403_error_page_renders_on_unauthorized_access(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($participant)
            ->get(route('organizer.dashboard'));

        $response->assertStatus(403);
        $response->assertSee('Access Denied');
        $response->assertSee('Explore Events');
    }

    /**
     * 3. Test production Artisan optimization commands execute cleanly without serialization exceptions.
     */
    public function test_production_artisan_optimization_commands_execute_without_error(): void
    {
        $configResult = Artisan::call('config:cache');
        $this->assertSame(0, $configResult);

        $routeResult = Artisan::call('route:cache');
        $this->assertSame(0, $routeResult);

        $viewResult = Artisan::call('view:cache');
        $this->assertSame(0, $viewResult);

        $clearResult = Artisan::call('optimize:clear');
        $this->assertSame(0, $clearResult);
    }

    /**
     * 4. Test .env.example contains all critical production deployment variables.
     */
    public function test_env_example_contains_all_critical_production_keys(): void
    {
        $envExamplePath = base_path('.env.example');
        $this->assertFileExists($envExamplePath);

        $content = File::get($envExamplePath);

        $this->assertStringContainsString('APP_NAME=', $content);
        $this->assertStringContainsString('APP_ENV=production', $content);
        $this->assertStringContainsString('APP_DEBUG=false', $content);
        $this->assertStringContainsString('DB_CONNECTION=', $content);
        $this->assertStringContainsString('SESSION_DRIVER=', $content);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $content);
        $this->assertStringContainsString('CACHE_STORE=', $content);
        $this->assertStringContainsString('QUEUE_CONNECTION=', $content);
    }

    /**
     * 5. Test storage public disk directory is present and writable for media and QR codes.
     */
    public function test_storage_public_disk_directory_is_configured_and_writable(): void
    {
        $publicStoragePath = storage_path('app/public');

        $this->assertDirectoryExists($publicStoragePath);
        $this->assertDirectoryIsWritable($publicStoragePath);

        // Verify public symlink target exists
        $publicSymlink = public_path('storage');
        $this->assertTrue(file_exists($publicSymlink) || is_link($publicSymlink));
    }
}
