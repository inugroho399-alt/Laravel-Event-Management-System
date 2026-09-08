<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerDashboardTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // ACCESS CONTROL
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_can_access_their_dashboard(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Organizer Dashboard');
    }

    public function test_admin_can_access_organizer_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Organizer Dashboard');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('organizer.dashboard'));

        $response->assertRedirect('/login');
    }

    public function test_participant_cannot_access_organizer_dashboard(): void
    {
        $participant = User::factory()->participant()->create();

        $response = $this->actingAs($participant)->get(route('organizer.dashboard'));

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. CORRECT TOTAL EVENTS COUNT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dashboard_shows_correct_total_events_count(): void
    {
        $organizer = User::factory()->organizer()->create();
        Event::factory()->count(3)->create(['organizer_id' => $organizer->id]);

        $otherOrganizer = User::factory()->organizer()->create();
        Event::factory()->count(5)->create(['organizer_id' => $otherOrganizer->id]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        // The organizer's own 3 events, not the other 5
        $response->assertSee('3');
        $response->assertViewHas('totalEvents', 3);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. CORRECT PUBLISHED EVENTS COUNT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dashboard_shows_correct_published_events_count(): void
    {
        $organizer = User::factory()->organizer()->create();
        Event::factory()->count(2)->published()->create(['organizer_id' => $organizer->id]);
        Event::factory()->draft()->create(['organizer_id' => $organizer->id]);
        Event::factory()->cancelled()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('publishedEvents', 2);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. CORRECT TOTAL REGISTRATIONS COUNT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dashboard_shows_correct_total_registrations_count(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        // 3 confirmed
        Registration::factory()->count(3)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        // 1 cancelled — must NOT count
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        // Registrations for another organizer's event — must NOT appear
        $otherEvent = Event::factory()->published()->create();
        $otherTicket = TicketType::factory()->create(['event_id' => $otherEvent->id]);
        Registration::factory()->count(2)->create([
            'event_id' => $otherEvent->id,
            'ticket_type_id' => $otherTicket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        // Only 3 active registrations (cancelled excluded, other org excluded)
        $response->assertViewHas('totalRegistrations', 3);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. CORRECT ATTENDED COUNT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dashboard_shows_correct_attended_count(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        Registration::factory()->count(2)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Attended,
        ]);
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalAttended', 2);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. ORGANIZER SEES ONLY THEIR OWN DATA
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_sees_only_their_own_events_not_other_organizers(): void
    {
        $organizer = User::factory()->organizer()->create(['name' => 'My Organizer']);
        $otherOrganizer = User::factory()->organizer()->create(['name' => 'Other Organizer']);

        $myEvent = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'My Exclusive Event',
        ]);
        $otherEvent = Event::factory()->published()->create([
            'organizer_id' => $otherOrganizer->id,
            'title' => 'Other Organizer Event',
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalEvents', 1);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. UPCOMING EVENTS LIST
    // ──────────────────────────────────────────────────────────────────────────

    public function test_upcoming_events_shows_only_future_published_events(): void
    {
        $organizer = User::factory()->organizer()->create();

        // Future published event — should appear
        $futureEvent = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Future Conference 2027',
            'start_date' => now()->addDays(10),
        ]);

        // Past event — should NOT appear in upcoming
        Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Past Conference',
            'start_date' => now()->subDays(5),
        ]);

        // Draft event — should NOT appear in upcoming
        Event::factory()->draft()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Draft Conference',
            'start_date' => now()->addDays(3),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Future Conference 2027');
        $response->assertDontSee('Past Conference');
        $response->assertDontSee('Draft Conference');
    }

    public function test_upcoming_events_are_capped_at_five(): void
    {
        $organizer = User::factory()->organizer()->create();

        // Create 7 upcoming published events
        Event::factory()->count(7)->published()->create([
            'organizer_id' => $organizer->id,
            'start_date' => now()->addDays(10),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $upcomingEvents = $response->viewData('upcomingEvents');
        $this->assertLessThanOrEqual(5, $upcomingEvents->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11. RECENT REGISTRATIONS FEED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_recent_registrations_feed_is_displayed(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->participant()->create(['name' => 'Feed Tester']);

        Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Feed Tester');
        $response->assertSee('Recent Registrations');
    }

    public function test_recent_registrations_feed_is_capped_at_five(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        Registration::factory()->count(8)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $response->viewData('recentRegistrations')->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12. RECENT CHECK-INS FEED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_recent_check_ins_feed_is_displayed(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->participant()->create(['name' => 'CheckIn Person']);

        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Attended,
        ]);
        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now(),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('CheckIn Person');
        $response->assertSee('Recent Check-ins');
    }

    public function test_recent_check_ins_feed_is_capped_at_five(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $registrations = Registration::factory()->count(8)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Attended,
        ]);
        foreach ($registrations as $reg) {
            CheckIn::create([
                'registration_id' => $reg->id,
                'checked_in_by' => $organizer->id,
                'checked_in_at' => now(),
            ]);
        }

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $response->viewData('recentCheckIns')->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 13. QUICK ACTION LINKS PRESENT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dashboard_shows_quick_action_links(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Quick Actions');
        $response->assertSee('Create Event');
        $response->assertSee('Manage Events');
        $response->assertSee('Public Events');
        $response->assertSee('My Profile');
        $response->assertSee(route('organizer.events.create'));
        $response->assertSee(route('organizer.events.index'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14. REVENUE METRIC CALCULATED CORRECTLY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_revenue_is_calculated_from_confirmed_and_attended_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $vipTicket = TicketType::factory()->create(['event_id' => $event->id, 'price' => 100.00]);
        $gaTicket = TicketType::factory()->create(['event_id' => $event->id, 'price' => 50.00]);

        // 2 × $100 VIP confirmed
        Registration::factory()->count(2)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $vipTicket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        // 1 × $50 GA attended
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $gaTicket->id,
            'status' => RegistrationStatus::Attended,
        ]);
        // 1 × $100 VIP cancelled — must NOT count toward revenue
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $vipTicket->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        // 2×100 + 1×50 = $250.00
        $response->assertViewHas('totalRevenue', 250.00);
        $response->assertSee('250.00');
    }

    public function test_revenue_excludes_other_organizers_events(): void
    {
        $organizer = User::factory()->organizer()->create();
        $otherOrganizer = User::factory()->organizer()->create();

        $myEvent = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $myTicket = TicketType::factory()->create(['event_id' => $myEvent->id, 'price' => 200.00]);
        Registration::factory()->create([
            'event_id' => $myEvent->id,
            'ticket_type_id' => $myTicket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $otherEvent = Event::factory()->published()->create(['organizer_id' => $otherOrganizer->id]);
        $otherTicket = TicketType::factory()->create(['event_id' => $otherEvent->id, 'price' => 9999.00]);
        Registration::factory()->create([
            'event_id' => $otherEvent->id,
            'ticket_type_id' => $otherTicket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        // Only the organizer's own $200 revenue
        $response->assertViewHas('totalRevenue', 200.00);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ADMIN SEE ALL EVENTS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_dashboard_shows_all_events_across_organizers(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();

        Event::factory()->count(2)->create(['organizer_id' => $organizer1->id]);
        Event::factory()->count(3)->create(['organizer_id' => $organizer2->id]);

        $response = $this->actingAs($admin)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        // Admin sees all 5 events
        $response->assertViewHas('totalEvents', 5);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // EMPTY STATE
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dashboard_shows_empty_state_when_organizer_has_no_events(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalEvents', 0);
        $response->assertViewHas('publishedEvents', 0);
        $response->assertViewHas('totalRegistrations', 0);
        $response->assertViewHas('totalAttended', 0);
        $response->assertViewHas('totalRevenue', 0);
        $response->assertSee('No upcoming events scheduled.');
        $response->assertSee('No registrations yet.');
        $response->assertSee('No check-ins recorded yet.');
    }
}
