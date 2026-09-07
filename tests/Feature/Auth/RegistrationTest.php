<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_cannot_register_with_invalid_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_users_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'duplicate@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'Another User',
            'email' => 'duplicate@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_users_cannot_register_without_matching_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'password',
            'password_confirmation' => 'mismatch-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_required_registration_fields_are_validated(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
    }

    public function test_password_minimum_length_is_enforced(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'shortpass@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_passwords_are_stored_securely_in_database_upon_registration(): void
    {
        $this->post('/register', [
            'name' => 'Secure User',
            'email' => 'secure@example.com',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ]);

        $user = User::where('email', 'secure@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotEquals('secure-password-123', $user->password);
        $this->assertTrue(Hash::check('secure-password-123', $user->password));
    }
}
