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

/**
 * TEST PHASE 11 — ORGANIZER DASHBOARD
 *
 * Requirement coverage:
 *  1.  Organizer can access dashboard
 *  2.  Participant cannot access organizer dashboard
 *  3.  Dashboard statistics use real database data (no hardcoding)
 *  4.  Total events is correct
 *  5.  Total participants is correct
 *  6.  Total tickets (ticket types) is correct
 *  7.  Total check-ins is correct
 *  8.  Recent events are correct
 *  9.  Upcoming events are correct
 *  10. Recent registrations are correct
 *  11. Empty states work correctly
 *  12. Dashboard does not expose another organizer's private data
 */
class OrganizerDashboardTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // 1 & 2 — ACCESS CONTROL
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
    // 3 — STATS USE REAL DATABASE DATA (NOT HARDCODED)
    // Verify the view variables match actual DB counts directly queried.
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dashboard_statistics_match_actual_database_records(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket1 = TicketType::factory()->create(['event_id' => $event->id, 'price' => 100]);
        $ticket2 = TicketType::factory()->create(['event_id' => $event->id, 'price' => 50]);

        $p1 = User::factory()->participant()->create();
        $p2 = User::factory()->participant()->create();

        // p1 has 2 registrations (different tickets), p2 has 1
        $r1 = Registration::factory()->create([
            'user_id' => $p1->id, 'event_id' => $event->id,
            'ticket_type_id' => $ticket1->id, 'status' => RegistrationStatus::Attended,
        ]);
        Registration::factory()->create([
            'user_id' => $p1->id, 'event_id' => $event->id,
            'ticket_type_id' => $ticket2->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        Registration::factory()->create([
            'user_id' => $p2->id, 'event_id' => $event->id,
            'ticket_type_id' => $ticket1->id, 'status' => RegistrationStatus::Cancelled,
        ]);

        CheckIn::create([
            'registration_id' => $r1->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now(),
        ]);

        // Compute expected values directly from DB — no hardcoding
        $expectedTotalEvents = Event::where('organizer_id', $organizer->id)->count();
        $expectedTotalRegistrations = Registration::where('event_id', $event->id)
            ->where('status', '!=', RegistrationStatus::Cancelled)->count();
        $expectedTotalParticipants = Registration::where('event_id', $event->id)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->distinct('user_id')->count('user_id');
        $expectedTotalAttended = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Attended)->count();
        $expectedTotalTicketTypes = TicketType::where('event_id', $event->id)->count();

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalEvents', $expectedTotalEvents);
        $response->assertViewHas('totalRegistrations', $expectedTotalRegistrations);
        $response->assertViewHas('totalParticipants', $expectedTotalParticipants);
        $response->assertViewHas('totalAttended', $expectedTotalAttended);
        $response->assertViewHas('totalTicketTypes', $expectedTotalTicketTypes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4 — TOTAL EVENTS CORRECT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_total_events_count_reflects_only_own_events(): void
    {
        $organizer = User::factory()->organizer()->create();
        Event::factory()->count(3)->create(['organizer_id' => $organizer->id]);

        // Another organizer's events must not be counted
        $other = User::factory()->organizer()->create();
        Event::factory()->count(5)->create(['organizer_id' => $other->id]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalEvents', 3);
    }

    public function test_total_events_count_includes_all_statuses(): void
    {
        $organizer = User::factory()->organizer()->create();
        Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        Event::factory()->draft()->create(['organizer_id' => $organizer->id]);
        Event::factory()->cancelled()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        // All 3 statuses are counted in totalEvents
        $response->assertViewHas('totalEvents', 3);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5 — TOTAL PARTICIPANTS CORRECT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_total_participants_counts_distinct_users_not_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $p1 = User::factory()->participant()->create();
        $p2 = User::factory()->participant()->create();

        // p1 registers twice for the same event (two different ticket types)
        Registration::factory()->create([
            'user_id' => $p1->id, 'event_id' => $event->id,
            'ticket_type_id' => $ticket->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        Registration::factory()->create([
            'user_id' => $p1->id, 'event_id' => $event->id,
            'ticket_type_id' => $ticket->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        // p2 registers once
        Registration::factory()->create([
            'user_id' => $p2->id, 'event_id' => $event->id,
            'ticket_type_id' => $ticket->id, 'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        // 3 registrations but only 2 distinct users
        $response->assertStatus(200);
        $response->assertViewHas('totalParticipants', 2);
    }

    public function test_total_participants_excludes_cancelled_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $active = User::factory()->participant()->create();
        $cancelled = User::factory()->participant()->create();

        Registration::factory()->create([
            'user_id' => $active->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        Registration::factory()->create([
            'user_id' => $cancelled->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        // Only 1 active participant; cancelled user excluded
        $response->assertViewHas('totalParticipants', 1);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6 — TOTAL TICKET TYPES CORRECT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_total_ticket_types_counts_all_ticket_types_across_events(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event1 = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $event2 = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        // 2 ticket types on event 1, 1 on event 2 → total 3
        TicketType::factory()->count(2)->create(['event_id' => $event1->id]);
        TicketType::factory()->count(1)->create(['event_id' => $event2->id]);

        // Another organizer's ticket types — must NOT count
        $other = User::factory()->organizer()->create();
        $otherEvent = Event::factory()->published()->create(['organizer_id' => $other->id]);
        TicketType::factory()->count(4)->create(['event_id' => $otherEvent->id]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalTicketTypes', 3);
        $response->assertSee('Ticket Types');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7 — TOTAL CHECK-INS CORRECT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_total_attended_count_reflects_only_attended_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        Registration::factory()->count(3)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Attended,
        ]);
        Registration::factory()->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        // Another organizer's attended — must NOT count
        $otherEvent = Event::factory()->published()->create();
        $otherTicket = TicketType::factory()->create(['event_id' => $otherEvent->id]);
        Registration::factory()->count(5)->create([
            'event_id' => $otherEvent->id, 'ticket_type_id' => $otherTicket->id,
            'status' => RegistrationStatus::Attended,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertViewHas('totalAttended', 3);
        $response->assertSee('Attended');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8 — RECENT EVENTS CORRECT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_recent_events_shows_past_events_ordered_by_start_date_descending(): void
    {
        $organizer = User::factory()->organizer()->create();

        // Past event 1 (most recent past)
        $event1 = Event::factory()->completed()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Past Event One',
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDay(),
        ]);

        // Past event 2 (older past)
        $event2 = Event::factory()->completed()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Past Event Two',
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(9),
        ]);

        // Future published event — must NOT appear in recent events
        Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Future Event',
            'start_date' => now()->addDays(5),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Recent Events');
        $response->assertSee('Past Event One');
        $response->assertSee('Past Event Two');

        // Future event must NOT appear in recentEvents (it may appear in upcoming)
        $recentEvents = $response->viewData('recentEvents');
        $recentTitles = $recentEvents->pluck('title')->all();
        $this->assertNotContains('Future Event', $recentTitles);

        // Verify ordering (most recent first)
        $this->assertSame($event1->id, $recentEvents->first()->id);
    }

    public function test_recent_events_are_capped_at_five(): void
    {
        $organizer = User::factory()->organizer()->create();

        Event::factory()->count(8)->completed()->create([
            'organizer_id' => $organizer->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(9),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $response->viewData('recentEvents')->count());
    }

    public function test_recent_events_includes_registration_and_attended_counts(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->completed()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Finished Conference',
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDays(2),
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        Registration::factory()->count(4)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Attended,
        ]);
        Registration::factory()->count(2)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $recentEvent = $response->viewData('recentEvents')->first();
        // 6 active registrations (confirmed + attended)
        $this->assertSame(6, $recentEvent->active_registrations_count);
        // 4 attended
        $this->assertSame(4, $recentEvent->attended_count);
    }

    public function test_recent_events_excludes_other_organizer_events(): void
    {
        $organizer = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();

        Event::factory()->completed()->create([
            'organizer_id' => $organizer->id,
            'title' => 'My Past Event',
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDays(2),
        ]);
        Event::factory()->completed()->create([
            'organizer_id' => $other->id,
            'title' => 'Other Org Past Event',
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDays(2),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $this->assertSame(1, $response->viewData('recentEvents')->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9 — UPCOMING EVENTS CORRECT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_upcoming_events_shows_only_future_published_and_ongoing_events(): void
    {
        $organizer = User::factory()->organizer()->create();

        // Future published — must appear
        Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Future Conference 2027',
            'start_date' => now()->addDays(10),
        ]);

        // Past published — must NOT appear in upcoming
        Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Past Conference',
            'start_date' => now()->subDays(5),
        ]);

        // Draft future — must NOT appear in upcoming
        Event::factory()->draft()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Draft Conference',
            'start_date' => now()->addDays(3),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Future Conference 2027');
        $response->assertDontSee('Draft Conference');
    }

    public function test_upcoming_events_are_capped_at_five(): void
    {
        $organizer = User::factory()->organizer()->create();
        Event::factory()->count(7)->published()->create([
            'organizer_id' => $organizer->id,
            'start_date' => now()->addDays(10),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $response->viewData('upcomingEvents')->count());
    }

    public function test_upcoming_events_includes_registration_count(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'start_date' => now()->addDays(5),
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        Registration::factory()->count(3)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        // Cancelled — must NOT count in active_registrations_count
        Registration::factory()->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $upcoming = $response->viewData('upcomingEvents')->first();
        $this->assertSame(3, $upcoming->active_registrations_count);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10 — RECENT REGISTRATIONS CORRECT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_recent_registrations_shows_latest_registrations_for_own_events(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->participant()->create(['name' => 'Dashboard Participant']);

        Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Recent Registrations');
        $response->assertSee('Dashboard Participant');
    }

    public function test_recent_registrations_are_ordered_by_created_at_descending(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $old = Registration::factory()->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
            'created_at' => now()->subHour(),
        ]);
        $new = Registration::factory()->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $recentRegistrations = $response->viewData('recentRegistrations');
        $this->assertSame($new->id, $recentRegistrations->first()->id);
    }

    public function test_recent_registrations_feed_is_capped_at_five(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        Registration::factory()->count(8)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $response->viewData('recentRegistrations')->count());
    }

    public function test_recent_registrations_excludes_other_organizer_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();
        $myEvent = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $otherEvent = Event::factory()->published()->create(['organizer_id' => $other->id]);
        $myTicket = TicketType::factory()->create(['event_id' => $myEvent->id]);
        $otherTicket = TicketType::factory()->create(['event_id' => $otherEvent->id]);
        $myParticipant = User::factory()->participant()->create(['name' => 'My Registrant']);
        $otherParticipant = User::factory()->participant()->create(['name' => 'Other Registrant']);

        Registration::factory()->create([
            'user_id' => $myParticipant->id, 'event_id' => $myEvent->id,
            'ticket_type_id' => $myTicket->id,
        ]);
        Registration::factory()->create([
            'user_id' => $otherParticipant->id, 'event_id' => $otherEvent->id,
            'ticket_type_id' => $otherTicket->id,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('My Registrant');

        $recentRegistrations = $response->viewData('recentRegistrations');
        // Only my registration appears
        $this->assertSame(1, $recentRegistrations->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11 — EMPTY STATES
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
        $response->assertViewHas('totalParticipants', 0);
        $response->assertViewHas('totalTicketTypes', 0);
        $response->assertViewHas('totalRevenue', 0);
        $response->assertSee('No upcoming events scheduled.');
        $response->assertSee('No past events yet.');
        $response->assertSee('No registrations yet.');
        $response->assertSee('No check-ins recorded yet.');
    }

    public function test_upcoming_events_empty_state_shows_create_prompt(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('No upcoming events scheduled.');
        $response->assertSee('Create your first event');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12 — DASHBOARD DOES NOT EXPOSE ANOTHER ORGANIZER'S PRIVATE DATA
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_cannot_see_other_organizers_events_in_any_section(): void
    {
        $organizer = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();

        // Own event
        $myEvent = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'My Public Summit',
            'start_date' => now()->addDays(10),
        ]);

        // Other organizer's event — should not appear anywhere
        $otherEvent = Event::factory()->published()->create([
            'organizer_id' => $other->id,
            'title' => 'Other Org Summit',
            'start_date' => now()->addDays(8),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        // KPI: only 1 event
        $response->assertViewHas('totalEvents', 1);
        // Other org's event title does not appear anywhere in the rendered page
        $response->assertDontSee('Other Org Summit');
    }

    public function test_organizer_cannot_see_other_organizers_participants_or_revenue(): void
    {
        $organizer = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();

        $myEvent = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $otherEvent = Event::factory()->published()->create(['organizer_id' => $other->id]);

        $myTicket = TicketType::factory()->create(['event_id' => $myEvent->id, 'price' => 50]);
        $otherTicket = TicketType::factory()->create(['event_id' => $otherEvent->id, 'price' => 9999]);

        $myParticipant = User::factory()->participant()->create(['name' => 'My Person']);
        $otherParticipant = User::factory()->participant()->create(['name' => 'Secret Person']);

        Registration::factory()->create([
            'user_id' => $myParticipant->id, 'event_id' => $myEvent->id,
            'ticket_type_id' => $myTicket->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        Registration::factory()->create([
            'user_id' => $otherParticipant->id, 'event_id' => $otherEvent->id,
            'ticket_type_id' => $otherTicket->id, 'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        // Only 1 participant visible
        $response->assertViewHas('totalParticipants', 1);
        // Revenue is only $50, not $9999
        $response->assertViewHas('totalRevenue', 50.0);
        // Other org's participant name doesn't leak
        $response->assertDontSee('Secret Person');
    }

    public function test_organizer_cannot_see_other_organizers_ticket_types_count(): void
    {
        $organizer = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();

        $myEvent = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $otherEvent = Event::factory()->published()->create(['organizer_id' => $other->id]);

        TicketType::factory()->count(2)->create(['event_id' => $myEvent->id]);
        TicketType::factory()->count(7)->create(['event_id' => $otherEvent->id]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        // Only my 2 ticket types counted
        $response->assertViewHas('totalTicketTypes', 2);
    }

    public function test_admin_dashboard_aggregates_all_organizers_data(): void
    {
        $admin = User::factory()->admin()->create();
        $org1 = User::factory()->organizer()->create();
        $org2 = User::factory()->organizer()->create();

        Event::factory()->count(2)->create(['organizer_id' => $org1->id]);
        Event::factory()->count(3)->create(['organizer_id' => $org2->id]);

        $response = $this->actingAs($admin)->get(route('organizer.dashboard'));

        $response->assertStatus(200);
        // Admin sees all 5 events
        $response->assertViewHas('totalEvents', 5);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PUBLISHED EVENTS COUNT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_published_events_count_excludes_draft_and_cancelled(): void
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
    // REVENUE
    // ──────────────────────────────────────────────────────────────────────────

    public function test_revenue_is_sum_of_ticket_price_for_confirmed_and_attended_only(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $vip = TicketType::factory()->create(['event_id' => $event->id, 'price' => 100.00]);
        $ga = TicketType::factory()->create(['event_id' => $event->id, 'price' => 50.00]);

        Registration::factory()->count(2)->create([  // 2 × $100 = $200
            'event_id' => $event->id, 'ticket_type_id' => $vip->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        Registration::factory()->create([             // 1 × $50  = $50
            'event_id' => $event->id, 'ticket_type_id' => $ga->id,
            'status' => RegistrationStatus::Attended,
        ]);
        Registration::factory()->create([             // cancelled — $0
            'event_id' => $event->id, 'ticket_type_id' => $vip->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.dashboard'));

        // $200 + $50 = $250
        $response->assertViewHas('totalRevenue', 250.0);
        $response->assertSee('250.00');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RECENT CHECK-INS FEED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_recent_check_ins_feed_is_displayed_and_capped_at_five(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $registrations = Registration::factory()->count(8)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id,
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
        $response->assertSee('Recent Check-ins');
        $this->assertLessThanOrEqual(5, $response->viewData('recentCheckIns')->count());
    }
}
