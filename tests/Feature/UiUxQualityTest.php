<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEST PHASE 14 — UI/UX QUALITY REVIEW
 *
 * Tests the application across Desktop, Tablet, and Mobile view considerations:
 *  1.  Navigation works across roles (Guest, Participant, Organizer, Admin)
 *  2.  Sidebar works (Desktop persistent + Mobile collapsible drawer)
 *  3.  Forms are usable and render required controls
 *  4.  Tables are responsive with overflow-x-auto wrappers
 *  5.  Buttons work with proper bindings
 *  6.  Links work and resolve to valid endpoints
 *  7.  Validation errors are clearly displayed
 *  8.  Success messages / flash toasts are displayed
 *  9.  Error states (404, 403, invalid data) are handled
 *  10. Empty states are handled cleanly with helpful CTAs
 *  11. Loading / Interactive states work where applicable
 *  12. Text does not overflow (containment & truncation classes)
 *  13. Cards do not break on mobile (responsive grid columns)
 *  14. QR Code remains usable and scannable on mobile
 *  15. Dashboard remains usable on small screens
 *  16. No obvious console / template errors
 *  17. No broken images / assets (fallbacks present)
 *  18. UI is visually consistent across all spaces
 */
class UiUxQualityTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // 1. NAVIGATION WORKS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_guest_navigation_renders_login_register_and_discovery(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Log in');
        $response->assertSee('Register');
        $response->assertSee('Find your next extraordinary experience');
    }

    public function test_participant_navigation_shows_attendee_links(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($participant)->get('/events');
        $response->assertStatus(200);
        $response->assertSee('My Registrations');
        $response->assertSee('Discover Events');
        $response->assertDontSee('Organizer Dashboard');
        $response->assertDontSee('Administrator Dashboard');
    }

    public function test_organizer_navigation_shows_organizer_links(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);

        $response = $this->actingAs($organizer)->get('/organizer/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Organizer Dashboard');
        $response->assertSee('Manage Events');
        $response->assertSee('Reports');
    }

    public function test_admin_navigation_shows_platform_oversight_links(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Admin Dashboard');
        $response->assertSee('Users');
        $response->assertSee('All Events');
        $response->assertSee('Registrations');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2 & 15. SIDEBAR WORKS & DASHBOARD USABLE ON SMALL SCREENS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_dashboard_contains_desktop_sidebar_and_mobile_toggle(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);

        $response = $this->actingAs($organizer)->get('/organizer/dashboard');
        $response->assertStatus(200);

        // Persistent desktop sidebar
        $response->assertSee('lg:w-64');
        $response->assertSee('shrink-0');

        // Mobile / tablet collapsible drawer with Alpine.js
        $response->assertSee('x-data="{ mobileNavOpen: false }"', false);
        $response->assertSee('@click="mobileNavOpen = !mobileNavOpen"', false);

        // Navigation links within sidebar
        $response->assertSee(route('organizer.dashboard'));
        $response->assertSee(route('organizer.events.index'));
        $response->assertSee(route('organizer.reports.index'));
    }

    public function test_admin_dashboard_contains_desktop_sidebar_and_mobile_toggle(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertStatus(200);

        // Persistent desktop sidebar
        $response->assertSee('lg:w-64');
        $response->assertSee('shrink-0');

        // Mobile / tablet collapsible drawer with Alpine.js
        $response->assertSee('x-data="{ mobileNavOpen: false }"', false);
        $response->assertSee('@click="mobileNavOpen = !mobileNavOpen"', false);

        // Platform oversight links
        $response->assertSee(route('admin.dashboard'));
        $response->assertSee(route('admin.users.index'));
        $response->assertSee(route('admin.events.index'));
        $response->assertSee(route('admin.registrations.index'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. FORMS ARE USABLE
    // ──────────────────────────────────────────────────────────────────────────

    public function test_event_creation_form_renders_all_controls(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);

        $response = $this->actingAs($organizer)->get('/organizer/events/create');
        $response->assertStatus(200);
        $response->assertSee('name="title"', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('name="location"', false);
        $response->assertSee('name="start_date"', false);
        $response->assertSee('name="end_date"', false);
        $response->assertSee('name="status"', false);
        $response->assertSee('name="banner_image"', false);
        $response->assertSee('Save &amp; Create Event', false);
    }

    public function test_ticket_creation_form_renders_all_controls(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->get("/organizer/events/{$event->id}/tickets/create");
        $response->assertStatus(200);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="price"', false);
        $response->assertSee('name="quota"', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('Save Ticket Type');
    }

    public function test_check_in_form_renders_scanner_input(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($organizer)->get("/organizer/events/{$event->id}/check-in");
        $response->assertStatus(200);
        $response->assertSee('name="registration_code"', false);
        $response->assertSee('EVENT-REG-XXXXXXXX');
        $response->assertSee('Check In Attendee');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. TABLES ARE RESPONSIVE
    // ──────────────────────────────────────────────────────────────────────────

    public function test_all_data_tables_have_responsive_overflow_wrappers(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => EventStatus::Published]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create(['role' => UserRole::Participant]);
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'user_id' => $user->id,
        ]);

        // 1. Organizer Events Table
        $resEvents = $this->actingAs($organizer)->get('/organizer/events');
        $resEvents->assertSee('overflow-x-auto');

        // 2. Organizer Tickets Table
        $resTickets = $this->actingAs($organizer)->get("/organizer/events/{$event->id}/tickets");
        $resTickets->assertSee('overflow-x-auto');

        // 3. Organizer Attendees Table
        $resAttendees = $this->actingAs($organizer)->get("/organizer/events/{$event->id}/registrations");
        $resAttendees->assertSee('overflow-x-auto');

        // 4. Organizer Reports Table
        $resReports = $this->actingAs($organizer)->get('/organizer/reports');
        $resReports->assertSee('overflow-x-auto');

        // 5. Admin Users Table
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $resUsers = $this->actingAs($admin)->get('/admin/users');
        $resUsers->assertSee('overflow-x-auto');

        // 6. Admin Events Table
        $resAdminEvents = $this->actingAs($admin)->get('/admin/events');
        $resAdminEvents->assertSee('overflow-x-auto');

        // 7. Admin Registrations Table
        $resAdminRegs = $this->actingAs($admin)->get('/admin/registrations');
        $resAdminRegs->assertSee('overflow-x-auto');

        // 8. Participant Registrations Table
        $resMyRegs = $this->actingAs($user)->get('/my-registrations');
        $resMyRegs->assertSee('overflow-x-auto');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5 & 6. BUTTONS AND LINKS WORK
    // ──────────────────────────────────────────────────────────────────────────

    public function test_key_action_links_and_buttons_resolve_successfully(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => EventStatus::Published]);

        // Organizer routes
        $this->actingAs($organizer)->get(route('organizer.events.index'))->assertStatus(200);
        $this->actingAs($organizer)->get(route('organizer.events.create'))->assertStatus(200);
        $this->actingAs($organizer)->get(route('organizer.events.edit', $event))->assertStatus(200);
        $this->actingAs($organizer)->get(route('organizer.events.tickets.index', $event))->assertStatus(200);
        $this->actingAs($organizer)->get(route('organizer.events.registrations.index', $event))->assertStatus(200);
        $this->actingAs($organizer)->get(route('organizer.events.check-in.create', $event))->assertStatus(200);
        $this->actingAs($organizer)->get(route('organizer.events.reports.show', $event))->assertStatus(200);
        $this->actingAs($organizer)->get(route('organizer.reports.index'))->assertStatus(200);

        // Public routes
        $this->get(route('events.index'))->assertStatus(200);
        $this->get(route('events.show', $event))->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. VALIDATION ERRORS DISPLAYED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_event_validation_errors_are_clearly_displayed(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);

        $response = $this->actingAs($organizer)->post('/organizer/events', [
            'title' => '',
            'start_date' => '',
            'end_date' => '',
            'status' => 'invalid-status',
        ]);

        $response->assertSessionHasErrors(['title', 'start_date', 'end_date', 'status']);
    }

    public function test_check_in_error_is_displayed_on_invalid_code(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($organizer)->post("/organizer/events/{$event->id}/check-in", [
            'registration_code' => 'INVALID-CODE-999',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. SUCCESS MESSAGES / FLASH TOASTS DISPLAYED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_flash_toast_component_is_present_in_layout(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($participant)
            ->withSession(['status' => 'Event registration successfully confirmed!'])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Event registration successfully confirmed!');
        $response->assertSee('x-data="{ show: true }"', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. ERROR STATES HANDLED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_error_states_are_handled_safely(): void
    {
        // 404 for missing event
        $this->get('/events/non-existent-event-slug-xyz')->assertStatus(404);

        // 403 for unauthorized participant accessing organizer
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $this->actingAs($participant)->get('/organizer/dashboard')->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. EMPTY STATES HANDLED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_empty_catalog_displays_friendly_empty_state(): void
    {
        $response = $this->get('/events');
        $response->assertStatus(200);
        $response->assertSee('No events found');
        $response->assertSee('There are currently no published events available. Check back soon!');
    }

    public function test_empty_organizer_dashboard_displays_empty_states(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);

        $response = $this->actingAs($organizer)->get('/organizer/dashboard');
        $response->assertStatus(200);
        $response->assertSee('No upcoming events scheduled.');
        $response->assertSee('No registrations yet.');
        $response->assertSee('No check-ins recorded yet.');
    }

    public function test_empty_tickets_displays_first_ticket_prompt(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->get("/organizer/events/{$event->id}/tickets");
        $response->assertStatus(200);
        $response->assertSee('No ticket types created yet');
        $response->assertSee('Create First Ticket Type');
    }

    public function test_empty_registrations_displays_attendee_empty_state(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->get("/organizer/events/{$event->id}/registrations");
        $response->assertStatus(200);
        $response->assertSee('No attendees registered yet');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12. TEXT OVERFLOW PROTECTION
    // ──────────────────────────────────────────────────────────────────────────

    public function test_long_names_and_titles_use_truncation_or_break_words(): void
    {
        $organizer = User::factory()->create([
            'name' => 'Professor Alexandrina Montgomery-Wellington the Third of Cambridge',
            'role' => UserRole::Organizer,
        ]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Super Mega Comprehensive International Symposium on Cutting-Edge Quantum Artificial Neural Supercomputers',
            'status' => EventStatus::Published,
        ]);

        $response = $this->actingAs($organizer)->get('/organizer/dashboard');
        $response->assertStatus(200);
        $response->assertSee('truncate');
        $response->assertSee('min-w-0');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 13. CARDS DO NOT BREAK ON MOBILE
    // ──────────────────────────────────────────────────────────────────────────

    public function test_event_discovery_cards_use_responsive_grid(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        Event::factory()->count(3)->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Published,
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
        $response->assertSee('grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14. QR CODE REMAINS USABLE ON MOBILE
    // ──────────────────────────────────────────────────────────────────────────

    public function test_digital_ticket_renders_scannable_qr_with_mobile_support(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => EventStatus::Published]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($participant)->get("/my-registrations/{$registration->id}");
        $response->assertStatus(200);

        // Vector QR SVG present
        $response->assertSee('<svg', false);

        // Scannable target frame & viewport
        $response->assertSee('bg-white p-4 rounded-3xl');
        $response->assertSee('w-48 h-48');

        // One-click copy code button with interactive tooltip
        $response->assertSee('x-data="{ copied: false }"', false);
        $response->assertSee('navigator.clipboard.writeText');

        // Perforated boarding-pass notches
        $response->assertSee('bg-slate-50 rounded-full');

        // Print stylesheet
        $response->assertSee('print:shadow-none');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 17. BROKEN ASSETS / FALLBACKS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_event_without_banner_renders_graceful_gradient_fallback(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Published,
            'banner_image' => null, // No uploaded image
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
        $response->assertSee('from-slate-900 via-indigo-900 to-purple-800');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 18. UI VISUAL CONSISTENCY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_status_badge_component_is_used_consistently(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Published,
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // Event index has status badge
        $res1 = $this->get('/events');
        $res1->assertSee('Published');

        // Attendee roster has status badge
        $res2 = $this->actingAs($organizer)->get("/organizer/events/{$event->id}/registrations");
        $res2->assertSee('Confirmed');

        // Admin oversight has status badge
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $res3 = $this->actingAs($admin)->get('/admin/registrations');
        $res3->assertSee('Confirmed');
    }
}
