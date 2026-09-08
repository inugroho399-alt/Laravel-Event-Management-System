<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $organizerA;

    private User $organizerB;

    private User $participantA;

    private User $participantB;

    private Event $eventA;

    private TicketType $ticketA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->organizerA = User::factory()->create(['role' => UserRole::Organizer]);
        $this->organizerB = User::factory()->create(['role' => UserRole::Organizer]);
        $this->participantA = User::factory()->create(['role' => UserRole::Participant]);
        $this->participantB = User::factory()->create(['role' => UserRole::Participant]);

        $this->eventA = Event::factory()->create([
            'organizer_id' => $this->organizerA->id,
            'status' => EventStatus::Published,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
        ]);

        $this->ticketA = TicketType::factory()->create([
            'event_id' => $this->eventA->id,
            'name' => 'General Admission',
            'price' => 50.00,
            'quota' => 20,
        ]);
    }

    /**
     * 1. Test Enterprise Security Headers are attached to all responses.
     */
    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get(route('events.index'));

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
    }

    /**
     * 1b. Test CSRF Protection middleware is registered in the web middleware group.
     */
    public function test_csrf_middleware_is_included_in_web_middleware_stack(): void
    {
        $router = app('router');
        $middlewareGroups = $router->getMiddlewareGroups();

        $this->assertArrayHasKey('web', $middlewareGroups);
        $this->assertTrue(
            in_array(ValidateCsrfToken::class, $middlewareGroups['web'], true)
        );
    }

    /**
     * 2. Test Registration Mass Assignment cannot escalate to Admin role.
     */
    public function test_registration_rejects_admin_role_mass_assignment(): void
    {
        $response = $this->post('/register', [
            'name' => 'Malicious User',
            'email' => 'hacker@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'role' => UserRole::Admin->value,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'hacker@example.com']);
    }

    /**
     * 3. Test Profile Update ignores unauthorized role elevation payload.
     */
    public function test_profile_update_ignores_unauthorized_role_elevation(): void
    {
        $response = $this->actingAs($this->participantA)
            ->patch('/profile', [
                'name' => 'Legit Name',
                'email' => $this->participantA->email,
                'role' => UserRole::Admin->value,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Participant, $this->participantA->fresh()->role);
    }

    /**
     * 4. Test Event Creation overrides forged organizer_id.
     */
    public function test_event_creation_overrides_forged_organizer_id(): void
    {
        $response = $this->actingAs($this->organizerA)
            ->post(route('organizer.events.store'), [
                'title' => 'Spoofed Event',
                'description' => 'Trying to forge organizer_id',
                'location' => 'Convention Hall',
                'start_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'end_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'status' => EventStatus::Published->value,
                'organizer_id' => $this->organizerB->id, // Attempting to spoof organizer
            ]);

        $response->assertRedirect(route('organizer.events.index'));

        $event = Event::where('title', 'Spoofed Event')->first();
        $this->assertNotNull($event);
        $this->assertSame($this->organizerA->id, $event->organizer_id);
        $this->assertNotSame($this->organizerB->id, $event->organizer_id);
    }

    /**
     * 5. Test Ticket Creation binds strictly to the parent Event.
     */
    public function test_ticket_creation_strictly_belongs_to_route_event(): void
    {
        $eventB = Event::factory()->create(['organizer_id' => $this->organizerB->id]);

        $response = $this->actingAs($this->organizerA)
            ->post(route('organizer.events.tickets.store', $this->eventA), [
                'name' => 'Sneaky Ticket',
                'description' => 'Trying to attach to another event',
                'price' => 10.00,
                'quota' => 50,
                'event_id' => $eventB->id, // Spoofed event_id
            ]);

        $response->assertRedirect(route('organizer.events.tickets.index', $this->eventA));

        $ticket = TicketType::where('name', 'Sneaky Ticket')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($this->eventA->id, $ticket->event_id);
        $this->assertNotSame($eventB->id, $ticket->event_id);
    }

    /**
     * 6. Test Registration stores the authenticated user's ID regardless of request payload.
     */
    public function test_registration_ignores_forged_user_id(): void
    {
        $response = $this->actingAs($this->participantA)
            ->post(route('events.register', $this->eventA), [
                'ticket_type_id' => $this->ticketA->id,
                'user_id' => $this->participantB->id, // Attempt to register as participant B
            ]);

        $response->assertRedirect(route('registrations.index'));

        $registration = Registration::where('event_id', $this->eventA->id)
            ->where('ticket_type_id', $this->ticketA->id)
            ->first();

        $this->assertNotNull($registration);
        $this->assertSame($this->participantA->id, $registration->user_id);
        $this->assertNotSame($this->participantB->id, $registration->user_id);
    }

    /**
     * 7. Test Participant is blocked with 403 from accessing organizer and admin routes.
     */
    public function test_participant_cannot_access_organizer_or_admin_routes(): void
    {
        $this->actingAs($this->participantA);

        $this->get(route('organizer.dashboard'))->assertStatus(403);
        $this->get(route('organizer.events.index'))->assertStatus(403);
        $this->get(route('organizer.reports.index'))->assertStatus(403);
        $this->get(route('admin.dashboard'))->assertStatus(403);
        $this->get(route('admin.users.index'))->assertStatus(403);
        $this->get(route('admin.events.index'))->assertStatus(403);
    }

    /**
     * 8. Test Organizer is blocked from accessing admin routes and other organizers' private event data.
     */
    public function test_organizer_cannot_access_admin_or_peer_organizer_private_data(): void
    {
        $this->actingAs($this->organizerA);

        // Admin isolation
        $this->get(route('admin.dashboard'))->assertStatus(403);
        $this->get(route('admin.users.index'))->assertStatus(403);

        // Peer organizer event isolation
        $eventB = Event::factory()->create(['organizer_id' => $this->organizerB->id]);

        $this->get(route('organizer.events.edit', $eventB))->assertStatus(403);
        $this->put(route('organizer.events.update', $eventB), ['title' => 'Hijacked'])->assertStatus(403);
        $this->delete(route('organizer.events.destroy', $eventB))->assertStatus(403);
        $this->get(route('organizer.events.reports.show', $eventB))->assertStatus(403);
        $this->get(route('organizer.events.check-in.create', $eventB))->assertStatus(403);
    }

    /**
     * 9. Test Participant cannot view or cancel another participant's digital ticket or registration.
     */
    public function test_participant_cannot_view_or_cancel_peer_registration(): void
    {
        $registrationA = Registration::factory()->create([
            'event_id' => $this->eventA->id,
            'user_id' => $this->participantA->id,
            'ticket_type_id' => $this->ticketA->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->actingAs($this->participantB);

        // View peer registration ticket
        $this->get(route('registrations.show', $registrationA))->assertStatus(403);

        // Cancel peer registration
        $this->post(route('registrations.cancel', $registrationA))->assertStatus(403);
        $this->assertSame(RegistrationStatus::Confirmed, $registrationA->fresh()->status);
    }

    /**
     * 10. Test Admin cannot accidentally demote or delete their own account.
     */
    public function test_admin_cannot_self_demote_or_self_delete(): void
    {
        $this->actingAs($this->admin);

        // Attempt self-demotion
        $demoteResponse = $this->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => UserRole::Participant->value,
        ]);

        $demoteResponse->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);

        // Attempt self-deletion
        $deleteResponse = $this->delete(route('admin.users.destroy', $this->admin));
        $deleteResponse->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    /**
     * 11. Test QR Code SVG encodes strictly registration code without leaking sensitive attendee credentials.
     */
    public function test_qr_code_svg_encodes_only_registration_code_without_sensitive_credentials(): void
    {
        $registration = Registration::factory()->create([
            'event_id' => $this->eventA->id,
            'user_id' => $this->participantA->id,
            'ticket_type_id' => $this->ticketA->id,
            'registration_code' => 'EVENT-REG-SECRET123',
        ]);

        $qrService = app(QrCodeService::class);
        $svgContent = $qrService->generateSvgString($registration->registration_code);

        // Verify SVG structure
        $this->assertStringContainsString('<svg', $svgContent);

        // Must NOT leak user email, password, or role
        $this->assertStringNotContainsString($this->participantA->email, $svgContent);
        $this->assertStringNotContainsString($this->participantA->password, $svgContent);
        $this->assertStringNotContainsString('password', strtolower($svgContent));
    }

    /**
     * 12. Test CSV Export neutralizes spreadsheet formula injection starters (=, +, -, @, \t, \r).
     */
    public function test_csv_export_neutralizes_formula_injection_characters(): void
    {
        $maliciousUser = User::factory()->create([
            'name' => "=HYPERLINK('http://malicious.site','Click Me')",
            'email' => '+1234567@example.com',
            'role' => UserRole::Participant,
        ]);

        Registration::factory()->create([
            'event_id' => $this->eventA->id,
            'user_id' => $maliciousUser->id,
            'ticket_type_id' => $this->ticketA->id,
            'registration_code' => '@MALICIOUS-CODE-01',
        ]);

        $response = $this->actingAs($this->organizerA)
            ->get(route('organizer.events.reports.export-attendees', $this->eventA));

        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Must prepend single quote (') to neutralize dangerous formula triggers
        $this->assertStringContainsString("'@MALICIOUS-CODE-01", $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'+1234567@example.com", $content);
    }

    /**
     * 13. Test User Model serialization hides password and remember_token.
     */
    public function test_user_model_serialization_hides_password_and_remember_token(): void
    {
        $this->admin->remember_token = 'secret_remember_token_123';
        $this->admin->save();

        $array = $this->admin->fresh()->toArray();
        $json = $this->admin->fresh()->toJson();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('secret_remember_token_123', $json);
    }

    /**
     * 14. Test Event Creation rejects SVG banner uploads to prevent stored XSS.
     */
    public function test_event_creation_rejects_svg_uploads_to_prevent_stored_xss(): void
    {
        $fakeSvg = UploadedFile::fake()->create('malicious.svg', 100, 'image/svg+xml');

        $response = $this->actingAs($this->organizerA)
            ->post(route('organizer.events.store'), [
                'title' => 'XSS Vector Event',
                'description' => 'Testing SVG rejection',
                'start_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'end_date' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'status' => EventStatus::Published->value,
                'banner_image' => $fakeSvg,
            ]);

        $response->assertSessionHasErrors('banner_image');
        $this->assertDatabaseMissing('events', ['title' => 'XSS Vector Event']);
    }

    /**
     * 15. Test Organizer cannot manipulate peer organizer's tickets via manipulated URL IDs.
     */
    public function test_organizer_cannot_view_or_manipulate_peer_tickets_via_manipulated_url(): void
    {
        $eventB = Event::factory()->create(['organizer_id' => $this->organizerB->id]);
        $ticketB = TicketType::factory()->create(['event_id' => $eventB->id, 'quota' => 20]);

        $this->actingAs($this->organizerA);

        // Attempt to edit ticketB using eventA's URL
        $this->get(route('organizer.events.tickets.edit', [$this->eventA, $ticketB]))->assertStatus(404);

        // Attempt to update ticketB using eventA's URL (FormRequest authorization blocks with 403)
        $this->put(route('organizer.events.tickets.update', [$this->eventA, $ticketB]), [
            'name' => 'Tampered',
            'price' => 5.00,
            'quota' => 100,
        ])->assertStatus(403);

        // Attempt to update ticketB using eventB's URL
        $this->put(route('organizer.events.tickets.update', [$eventB, $ticketB]), [
            'name' => 'Tampered',
            'price' => 5.00,
            'quota' => 100,
        ])->assertStatus(403);

        // Attempt to delete ticketB using eventA's URL
        $this->delete(route('organizer.events.tickets.destroy', [$this->eventA, $ticketB]))->assertStatus(404);

        // Attempt to delete ticketB using eventB's URL
        $this->delete(route('organizer.events.tickets.destroy', [$eventB, $ticketB]))->assertStatus(403);
    }

    /**
     * 16. Test Organizer cannot access peer organizer's attendee roster via manipulated event ID.
     */
    public function test_organizer_cannot_access_peer_attendee_roster_via_manipulated_event_id(): void
    {
        $eventB = Event::factory()->create(['organizer_id' => $this->organizerB->id]);

        $response = $this->actingAs($this->organizerA)
            ->get(route('organizer.events.registrations.index', $eventB));

        $response->assertStatus(403);
    }

    /**
     * 17. Test Organizer cannot export peer organizer's attendee CSV via manipulated event ID.
     */
    public function test_organizer_cannot_export_peer_attendees_csv_via_manipulated_event_id(): void
    {
        $eventB = Event::factory()->create(['organizer_id' => $this->organizerB->id]);

        $response = $this->actingAs($this->organizerA)
            ->get(route('organizer.events.reports.export-attendees', $eventB));

        $response->assertStatus(403);
    }
}
