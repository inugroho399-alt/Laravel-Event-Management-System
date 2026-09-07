<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test/organizer-only', fn () => response()->json(['ok' => true]))
            ->middleware(['web', 'auth', 'role:organizer']);

        Route::get('/test/admin-only', fn () => response()->json(['ok' => true]))
            ->middleware(['web', 'auth', 'role:admin']);
    }

    public function test_role_middleware_blocks_unauthenticated_users(): void
    {
        $response = $this->getJson('/test/organizer-only');
        $response->assertStatus(401);
    }

    public function test_role_middleware_blocks_unauthorized_roles(): void
    {
        $participant = User::factory()->participant()->create();

        $response = $this->actingAs($participant)->getJson('/test/organizer-only');
        $response->assertStatus(403);
    }

    public function test_role_middleware_allows_authorized_role(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->getJson('/test/organizer-only');
        $response->assertStatus(200);
    }

    public function test_role_middleware_allows_admin_super_access(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/test/organizer-only');
        $response->assertStatus(200);
    }

    public function test_organizer_cannot_access_admin_route(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->getJson('/test/admin-only');
        $response->assertStatus(403);
    }

    public function test_event_policy_authorization(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $admin = User::factory()->admin()->create();

        $event = Event::factory()->draft()->create(['organizer_id' => $organizer1->id]);

        // Event creation
        $this->assertTrue(Gate::forUser($organizer1)->allows('create', Event::class));
        $this->assertFalse(Gate::forUser($participant)->allows('create', Event::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Event::class));

        // Draft view
        $this->assertTrue(Gate::forUser($organizer1)->allows('view', $event));
        $this->assertFalse(Gate::forUser($organizer2)->allows('view', $event));
        $this->assertFalse(Gate::forUser($participant)->allows('view', $event));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $event));

        // Event update & delete
        $this->assertTrue(Gate::forUser($organizer1)->allows('update', $event));
        $this->assertFalse(Gate::forUser($organizer2)->allows('update', $event));
        $this->assertFalse(Gate::forUser($participant)->allows('update', $event));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $event));

        $this->assertTrue(Gate::forUser($organizer1)->allows('delete', $event));
        $this->assertFalse(Gate::forUser($organizer2)->allows('delete', $event));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $event));
    }

    public function test_ticket_type_policy_authorization(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $admin = User::factory()->admin()->create();

        $event = Event::factory()->create(['organizer_id' => $organizer1->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        // Ticket creation for event
        $this->assertTrue(Gate::forUser($organizer1)->allows('create', [TicketType::class, $event]));
        $this->assertFalse(Gate::forUser($organizer2)->allows('create', [TicketType::class, $event]));
        $this->assertTrue(Gate::forUser($admin)->allows('create', [TicketType::class, $event]));

        // Ticket update & delete
        $this->assertTrue(Gate::forUser($organizer1)->allows('update', $ticket));
        $this->assertFalse(Gate::forUser($organizer2)->allows('update', $ticket));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $ticket));

        $this->assertTrue(Gate::forUser($organizer1)->allows('delete', $ticket));
        $this->assertFalse(Gate::forUser($organizer2)->allows('delete', $ticket));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $ticket));
    }

    public function test_registration_policy_authorization(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant1 = User::factory()->participant()->create();
        $participant2 = User::factory()->participant()->create();
        $admin = User::factory()->admin()->create();

        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant1->id,
        ]);

        // Participant views their own registration
        $this->assertTrue(Gate::forUser($participant1)->allows('view', $registration));
        // Other participant cannot view
        $this->assertFalse(Gate::forUser($participant2)->allows('view', $registration));
        // Organizer of event can view
        $this->assertTrue(Gate::forUser($organizer)->allows('view', $registration));
        // Admin can view
        $this->assertTrue(Gate::forUser($admin)->allows('view', $registration));
    }

    public function test_check_in_policy_authorization(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $admin = User::factory()->admin()->create();

        $event = Event::factory()->create(['organizer_id' => $organizer1->id]);
        $registration = Registration::factory()->create(['event_id' => $event->id]);

        // Participant cannot perform check-in
        $this->assertFalse(Gate::forUser($participant)->allows('create', [CheckIn::class, $registration]));

        // Event organizer can perform check-in
        $this->assertTrue(Gate::forUser($organizer1)->allows('create', [CheckIn::class, $registration]));

        // Different organizer cannot perform check-in
        $this->assertFalse(Gate::forUser($organizer2)->allows('create', [CheckIn::class, $registration]));

        // Admin can perform check-in
        $this->assertTrue(Gate::forUser($admin)->allows('create', [CheckIn::class, $registration]));
    }

    public function test_user_cannot_self_register_as_admin(): void
    {
        $response = $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_user_can_self_register_as_organizer(): void
    {
        $response = $this->post('/register', [
            'name' => 'Valid Organizer',
            'email' => 'neworganizer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'organizer',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $user = User::where('email', 'neworganizer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals(UserRole::Organizer, $user->role);
    }
}
