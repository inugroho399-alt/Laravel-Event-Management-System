<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // 1. ACCESS CONTROL
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Administrator Dashboard');
        $response->assertSee('Platform Revenue');
        $response->assertSee('Manage Users');
        $response->assertSee('All Events');
    }

    public function test_organizer_cannot_access_admin_dashboard(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_admin_dashboard(): void
    {
        $participant = User::factory()->participant()->create();

        $response = $this->actingAs($participant)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_dashboard_and_is_redirected(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect('/login');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. SYSTEM-WIDE METRICS & AGGREGATIONS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_dashboard_shows_accurate_platform_metrics(): void
    {
        $admin = User::factory()->admin()->create();

        // 2 Organizers, 3 Participants
        $org1 = User::factory()->organizer()->create();
        $org2 = User::factory()->organizer()->create();
        $participants = User::factory()->count(3)->participant()->create();

        // Events across organizers
        $event1 = Event::factory()->published()->create(['organizer_id' => $org1->id]);
        $event2 = Event::factory()->draft()->create(['organizer_id' => $org2->id]);
        $event3 = Event::factory()->completed()->create(['organizer_id' => $org1->id]);

        $t1 = TicketType::factory()->create(['event_id' => $event1->id, 'price' => 100.00, 'quota' => 50]);
        $t2 = TicketType::factory()->create(['event_id' => $event2->id, 'price' => 50.00, 'quota' => 20]);
        $t3 = TicketType::factory()->create(['event_id' => $event3->id, 'price' => 20.00, 'quota' => 30]);

        // Registrations: 2 confirmed ($200), 1 attended ($20), 1 cancelled ($0)
        Registration::factory()->create([
            'user_id' => $participants[0]->id, 'event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        Registration::factory()->create([
            'user_id' => $participants[1]->id, 'event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        $attended = Registration::factory()->create([
            'user_id' => $participants[2]->id, 'event_id' => $event3->id, 'ticket_type_id' => $t3->id, 'status' => RegistrationStatus::Attended,
        ]);
        CheckIn::create([
            'registration_id' => $attended->id, 'checked_in_by' => $admin->id, 'checked_in_at' => now(),
        ]);
        Registration::factory()->create([
            'user_id' => $participants[0]->id, 'event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);

        // 1 admin + 2 organizers + 3 participants = 6 users
        $response->assertViewHas('totalUsers', 6);
        $response->assertViewHas('adminUsers', 1);
        $response->assertViewHas('organizerUsers', 2);
        $response->assertViewHas('participantUsers', 3);

        // Events
        $response->assertViewHas('totalEvents', 3);
        $response->assertViewHas('publishedEvents', 1);
        $response->assertViewHas('draftEvents', 1);
        $response->assertViewHas('completedEvents', 1);

        // Registrations & Check-ins
        $response->assertViewHas('totalRegistrations', 3); // 2 confirmed + 1 attended
        $response->assertViewHas('totalCheckIns', 1);
        $response->assertViewHas('attendedRegistrations', 1);
        $response->assertViewHas('cancelledRegistrations', 1);

        // Capacity: 50 + 20 + 30 = 100
        $response->assertViewHas('totalCapacity', 100);

        // Revenue: 2×100 + 1×20 = $220.00
        $response->assertViewHas('totalRevenue', 220.00);
    }

    public function test_admin_dashboard_shows_recent_activity_feeds(): void
    {
        $admin = User::factory()->admin()->create();

        $org = User::factory()->organizer()->create(['name' => 'Feed Organizer']);
        $event = Event::factory()->published()->create([
            'organizer_id' => $org->id,
            'title' => 'Global AI Summit',
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->participant()->create(['name' => 'Feed Participant']);

        $reg = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Attended,
        ]);
        CheckIn::create([
            'registration_id' => $reg->id,
            'checked_in_by' => $admin->id,
            'checked_in_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Global AI Summit');
        $response->assertSee('Feed Organizer');
        $response->assertSee('Feed Participant');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. USER MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_can_view_all_users_list(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Super Admin']);
        $organizer = User::factory()->organizer()->create(['name' => 'Event Host']);
        $participant = User::factory()->participant()->create(['name' => 'Regular Attendee']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertSee('User Management');
        $response->assertSee('Super Admin');
        $response->assertSee('Event Host');
        $response->assertSee('Regular Attendee');
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->organizer()->create(['name' => 'Organizer Only']);
        User::factory()->participant()->create(['name' => 'Participant Only']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'role' => UserRole::Organizer->value,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Organizer Only');
        $response->assertDontSee('Participant Only');
    }

    public function test_admin_can_search_users_by_name_or_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->participant()->create(['name' => 'Ada Lovelace', 'email' => 'ada@poetical.science']);
        User::factory()->participant()->create(['name' => 'Charles Babbage', 'email' => 'charles@difference.engine']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'search' => 'Lovelace',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Ada Lovelace');
        $response->assertDontSee('Charles Babbage');
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->participant()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role' => UserRole::Participant,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Jane Doe Promoted',
            'email' => 'jane@example.com',
            'role' => UserRole::Organizer->value,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jane Doe Promoted',
            'role' => UserRole::Organizer->value,
        ]);
    }

    public function test_admin_cannot_demote_their_own_administrator_account(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@system.local']);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Participant->value,
        ]);

        $response->assertSessionHasErrors(['role']);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $victim = User::factory()->participant()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $victim));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. GLOBAL EVENTS OVERSIGHT & MODERATION
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_can_view_all_events_across_organizers(): void
    {
        $admin = User::factory()->admin()->create();
        $org1 = User::factory()->organizer()->create(['name' => 'Org Alpha']);
        $org2 = User::factory()->organizer()->create(['name' => 'Org Beta']);

        $e1 = Event::factory()->published()->create(['organizer_id' => $org1->id, 'title' => 'Alpha Conference']);
        $e2 = Event::factory()->published()->create(['organizer_id' => $org2->id, 'title' => 'Beta Conference']);

        $response = $this->actingAs($admin)->get(route('admin.events.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Events Oversight');
        $response->assertSee('Alpha Conference');
        $response->assertSee('Beta Conference');
        $response->assertSee('Org Alpha');
        $response->assertSee('Org Beta');
    }

    public function test_admin_can_moderate_and_update_event_status(): void
    {
        $admin = User::factory()->admin()->create();
        $org = User::factory()->organizer()->create();
        $event = Event::factory()->draft()->create(['organizer_id' => $org->id]);

        $response = $this->actingAs($admin)->patch(route('admin.events.update-status', $event), [
            'status' => EventStatus::Published->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => EventStatus::Published->value,
        ]);
    }

    public function test_admin_can_delete_an_event(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->published()->create();

        $response = $this->actingAs($admin)->delete(route('admin.events.destroy', $event));

        $response->assertRedirect(route('admin.events.index'));
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. SECURITY & UNAUTHORIZED ACCESS PREVENTION
    // ──────────────────────────────────────────────────────────────────────────

    public function test_non_admin_cannot_access_user_or_event_management_routes(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $targetUser = User::factory()->participant()->create();
        $targetEvent = Event::factory()->published()->create();

        // Organizer blocked
        $this->actingAs($organizer)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($organizer)->get(route('admin.users.edit', $targetUser))->assertStatus(403);
        $this->actingAs($organizer)->put(route('admin.users.update', $targetUser), ['role' => 'admin'])->assertStatus(403);
        $this->actingAs($organizer)->delete(route('admin.users.destroy', $targetUser))->assertStatus(403);
        $this->actingAs($organizer)->get(route('admin.events.index'))->assertStatus(403);
        $this->actingAs($organizer)->patch(route('admin.events.update-status', $targetEvent), ['status' => 'cancelled'])->assertStatus(403);
        $this->actingAs($organizer)->delete(route('admin.events.destroy', $targetEvent))->assertStatus(403);

        // Participant blocked
        $this->actingAs($participant)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($participant)->get(route('admin.users.edit', $targetUser))->assertStatus(403);
        $this->actingAs($participant)->put(route('admin.users.update', $targetUser), ['role' => 'admin'])->assertStatus(403);
        $this->actingAs($participant)->delete(route('admin.users.destroy', $targetUser))->assertStatus(403);
        $this->actingAs($participant)->get(route('admin.events.index'))->assertStatus(403);
        $this->actingAs($participant)->patch(route('admin.events.update-status', $targetEvent), ['status' => 'cancelled'])->assertStatus(403);
        $this->actingAs($participant)->delete(route('admin.events.destroy', $targetEvent))->assertStatus(403);
    }

    public function test_generic_dashboard_redirects_admin_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.dashboard'));
        $response->assertSee('Redirecting to Administrator dashboard');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. GLOBAL REGISTRATIONS OVERSIGHT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_can_view_all_registrations(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $attendee = User::factory()->participant()->create(['name' => 'John von Neumann']);

        $reg = Registration::factory()->create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'registration_code' => 'EVENT-REG-JVN001',
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.registrations.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Ticket Registrations');
        $response->assertSee('EVENT-REG-JVN001');
        $response->assertSee('John von Neumann');
        $response->assertSee($event->title);
    }

    public function test_admin_can_filter_and_search_registrations(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $reg1 = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'registration_code' => 'EVENT-REG-FINDME',
            'status' => RegistrationStatus::Confirmed,
        ]);

        $reg2 = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'registration_code' => 'EVENT-REG-HIDEME',
            'status' => RegistrationStatus::Cancelled,
        ]);

        // Search by registration code
        $searchResponse = $this->actingAs($admin)->get(route('admin.registrations.index', [
            'search' => 'FINDME',
        ]));
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('EVENT-REG-FINDME');
        $searchResponse->assertDontSee('EVENT-REG-HIDEME');

        // Filter by status
        $filterResponse = $this->actingAs($admin)->get(route('admin.registrations.index', [
            'status' => RegistrationStatus::Cancelled->value,
        ]));
        $filterResponse->assertStatus(200);
        $filterResponse->assertSee('EVENT-REG-HIDEME');
        $filterResponse->assertDontSee('EVENT-REG-FINDME');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. SENSITIVE DATA EXPOSURE PROTECTION
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_views_do_not_expose_sensitive_information(): void
    {
        $admin = User::factory()->admin()->create([
            'remember_token' => 'super_secret_remember_token_xyz123',
        ]);
        $participant = User::factory()->participant()->create([
            'password' => '$2y$12$eX4mpL3H4sh3dPa55w0rdStr1ngD0N0tExP0s3',
            'remember_token' => 'participant_token_abc987',
        ]);

        $usersResponse = $this->actingAs($admin)->get(route('admin.users.index'));
        $usersResponse->assertStatus(200);
        $usersResponse->assertDontSee($participant->password);
        $usersResponse->assertDontSee('super_secret_remember_token_xyz123');
        $usersResponse->assertDontSee('participant_token_abc987');

        $editResponse = $this->actingAs($admin)->get(route('admin.users.edit', $participant));
        $editResponse->assertStatus(200);
        $editResponse->assertDontSee($participant->password);
        $editResponse->assertDontSee('participant_token_abc987');

        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertDontSee($admin->password);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. PAGINATION
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_pagination_works_for_users_events_and_registrations(): void
    {
        $admin = User::factory()->admin()->create();

        // 1. Users pagination (16 participants + 1 admin = 17 users > 15 per page)
        User::factory()->count(16)->participant()->create();
        $userResponse = $this->actingAs($admin)->get(route('admin.users.index', ['page' => 2]));
        $userResponse->assertStatus(200);
        $this->assertTrue($userResponse->viewData('users')->hasPages());

        // 2. Events pagination (16 events > 15 per page)
        Event::factory()->count(16)->published()->create(['organizer_id' => $admin->id]);
        $eventResponse = $this->actingAs($admin)->get(route('admin.events.index', ['page' => 2]));
        $eventResponse->assertStatus(200);
        $this->assertTrue($eventResponse->viewData('events')->hasPages());

        // 3. Registrations pagination (16 registrations > 15 per page)
        $event = Event::first();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        Registration::factory()->count(16)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
        ]);
        $regResponse = $this->actingAs($admin)->get(route('admin.registrations.index', ['page' => 2]));
        $regResponse->assertStatus(200);
        $this->assertTrue($regResponse->viewData('registrations')->hasPages());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. EMPTY STATES
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_empty_states_render_gracefully(): void
    {
        $admin = User::factory()->admin()->create();

        // Empty events
        $eventsResponse = $this->actingAs($admin)->get(route('admin.events.index'));
        $eventsResponse->assertStatus(200);
        $eventsResponse->assertSee('No events found matching your filter criteria.');

        // Empty registrations
        $regsResponse = $this->actingAs($admin)->get(route('admin.registrations.index'));
        $regsResponse->assertStatus(200);
        $regsResponse->assertSee('No registrations found matching your filter criteria.');

        // Empty search on users
        $usersResponse = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'nonexistent_user_query_123']));
        $usersResponse->assertStatus(200);
        $usersResponse->assertSee('No users found matching your criteria.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. DIRECT URL ACCESS AUTHORIZATION ENFORCEMENT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_direct_url_access_authorization_strictly_enforced_server_side(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        // 1. Guest attempting direct URL access -> 302 redirect to login
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
        $this->get('/admin/events')->assertRedirect('/login');
        $this->get('/admin/registrations')->assertRedirect('/login');

        // 2. Participant attempting direct URL access -> 403 Forbidden
        $this->actingAs($participant)->get('/admin/dashboard')->assertStatus(403);
        $this->actingAs($participant)->get('/admin/users')->assertStatus(403);
        $this->actingAs($participant)->get('/admin/events')->assertStatus(403);
        $this->actingAs($participant)->get('/admin/registrations')->assertStatus(403);

        // 3. Organizer attempting direct URL access -> 403 Forbidden
        $this->actingAs($organizer)->get('/admin/dashboard')->assertStatus(403);
        $this->actingAs($organizer)->get('/admin/users')->assertStatus(403);
        $this->actingAs($organizer)->get('/admin/events')->assertStatus(403);
        $this->actingAs($organizer)->get('/admin/registrations')->assertStatus(403);
    }
}
