<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_guests_cannot_access_protected_pages(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        $profileResponse = $this->get('/profile');
        $profileResponse->assertRedirect('/login');
    }

    public function test_authenticated_users_can_access_protected_pages(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $profileResponse = $this->actingAs($user)->get('/profile');
        $profileResponse->assertStatus(200);
    }

    public function test_session_behavior_regenerates_on_login_and_invalidates_on_logout(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $this->get('/login');
        $initialSessionId = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $authenticatedSessionId = session()->getId();
        $this->assertNotEquals($initialSessionId, $authenticatedSessionId);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->assertGuest();
    }

    public function test_passwords_are_stored_securely_using_hashing(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
        ]);

        $this->assertNotEquals('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }
}
