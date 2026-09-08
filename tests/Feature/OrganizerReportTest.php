<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerReportTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // 1. ACCESS CONTROL
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_can_access_reports_index(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Reports & Analytics');
        $response->assertSee('Export CSV Summary');
    }

    public function test_admin_can_access_reports_index(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('organizer.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Reports & Analytics');
    }

    public function test_guest_cannot_access_reports_index_and_is_redirected(): void
    {
        $response = $this->get(route('organizer.reports.index'));

        $response->assertRedirect('/login');
    }

    public function test_participant_cannot_access_reports_index(): void
    {
        $participant = User::factory()->participant()->create();

        $response = $this->actingAs($participant)->get(route('organizer.reports.index'));

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. DATA PRIVACY & SCOPING
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_only_sees_their_own_events_and_metrics_in_reports(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();

        $event1 = Event::factory()->published()->create([
            'organizer_id' => $organizer1->id,
            'title' => 'Organizer One Tech Summit',
        ]);
        $ticket1 = TicketType::factory()->create([
            'event_id' => $event1->id,
            'price' => 150.00,
            'quota' => 50,
        ]);
        Registration::factory()->count(2)->create([
            'event_id' => $event1->id,
            'ticket_type_id' => $ticket1->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $event2 = Event::factory()->published()->create([
            'organizer_id' => $organizer2->id,
            'title' => 'Organizer Two Secret Event',
        ]);
        $ticket2 = TicketType::factory()->create([
            'event_id' => $event2->id,
            'price' => 500.00,
            'quota' => 20,
        ]);
        Registration::factory()->count(5)->create([
            'event_id' => $event2->id,
            'ticket_type_id' => $ticket2->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer1)->get(route('organizer.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Organizer One Tech Summit');
        $response->assertDontSee('Organizer Two Secret Event');

        // Verify scoped numbers:
        $response->assertViewHas('totalEvents', 1);
        $response->assertViewHas('totalCapacity', 50);
        $response->assertViewHas('totalRegistrations', 2);
        $response->assertViewHas('totalRevenue', 300.00); // 2 × $150
    }

    public function test_admin_sees_aggregate_metrics_across_all_organizers(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();

        $event1 = Event::factory()->published()->create(['organizer_id' => $organizer1->id]);
        $event2 = Event::factory()->published()->create(['organizer_id' => $organizer2->id]);

        TicketType::factory()->create(['event_id' => $event1->id, 'quota' => 30]);
        TicketType::factory()->create(['event_id' => $event2->id, 'quota' => 70]);

        $response = $this->actingAs($admin)->get(route('organizer.reports.index'));

        $response->assertStatus(200);
        $response->assertViewHas('totalEvents', 2);
        $response->assertViewHas('totalCapacity', 100);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. FILTERING
    // ──────────────────────────────────────────────────────────────────────────

    public function test_reports_date_filtering_restricts_events_correctly(): void
    {
        $organizer = User::factory()->organizer()->create();

        $eventJan = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'January Kickoff',
            'start_date' => now()->startOfYear()->addDays(5),
        ]);

        $eventJul = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'July Midyear',
            'start_date' => now()->startOfYear()->addMonths(6),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index', [
            'date_from' => now()->startOfYear()->format('Y-m-d'),
            'date_to' => now()->startOfYear()->addMonths(1)->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $events = $response->viewData('events');
        $this->assertTrue($events->pluck('title')->contains('January Kickoff'));
        $this->assertFalse($events->pluck('title')->contains('July Midyear'));
        $response->assertViewHas('totalEvents', 1);
    }

    public function test_reports_status_filtering_restricts_events_correctly(): void
    {
        $organizer = User::factory()->organizer()->create();

        Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Active Summit',
        ]);

        Event::factory()->completed()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Closed Summit',
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index', [
            'status' => EventStatus::Published->value,
        ]));

        $response->assertStatus(200);
        $events = $response->viewData('events');
        $this->assertTrue($events->pluck('title')->contains('Active Summit'));
        $this->assertFalse($events->pluck('title')->contains('Closed Summit'));
        $response->assertViewHas('totalEvents', 1);
    }

    public function test_reports_event_dropdown_filter_restricts_to_single_event(): void
    {
        $organizer = User::factory()->organizer()->create();

        $event1 = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Focus Event',
        ]);

        $event2 = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Ignored Event',
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index', [
            'event_id' => $event1->id,
        ]));

        $response->assertStatus(200);
        $events = $response->viewData('events');
        $this->assertTrue($events->pluck('title')->contains('Focus Event'));
        $this->assertFalse($events->pluck('title')->contains('Ignored Event'));
        $response->assertViewHas('totalEvents', 1);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. METRIC ACCURACY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_reports_aggregate_calculations_are_accurate(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $vip = TicketType::factory()->create(['event_id' => $event->id, 'price' => 100.00, 'quota' => 10]);
        $ga = TicketType::factory()->create(['event_id' => $event->id, 'price' => 40.00, 'quota' => 20]);

        // 2 confirmed VIP ($200)
        Registration::factory()->count(2)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $vip->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // 2 attended GA ($80)
        $attendedRegs = Registration::factory()->count(2)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ga->id,
            'status' => RegistrationStatus::Attended,
        ]);
        foreach ($attendedRegs as $reg) {
            CheckIn::create([
                'registration_id' => $reg->id,
                'checked_in_by' => $organizer->id,
                'checked_in_at' => now(),
            ]);
        }

        // 1 cancelled GA ($0 revenue)
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ga->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));

        $response->assertStatus(200);

        // Capacity: 10 + 20 = 30
        $response->assertViewHas('totalCapacity', 30);

        // Active registrations: 2 + 2 = 4 (cancelled excluded)
        $response->assertViewHas('totalRegistrations', 4);

        // Attended: 2
        $response->assertViewHas('totalAttended', 2);

        // Attendance rate: 2 / 4 * 100 = 50.0%
        $response->assertViewHas('overallAttendanceRate', 50.0);

        // Capacity utilization: 4 / 30 * 100 = 13.3%
        $response->assertViewHas('capacityUtilization', 13.3);

        // Revenue: $200 + $80 = $280.00
        $response->assertViewHas('totalRevenue', 280.00);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. DETAILED EVENT REPORT (SHOW)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_can_view_detailed_report_for_their_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Deep Dive Conference',
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'price' => 75.00, 'quota' => 100]);

        $attendedReg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Attended,
        ]);
        CheckIn::create([
            'registration_id' => $attendedReg->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now(),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.reports.show', $event));

        $response->assertStatus(200);
        $response->assertSee('Deep Dive Conference');
        $response->assertSee('Export Attendees CSV');
        $response->assertSee('Ticket Tier Breakdown');
        $response->assertSee('Check-in Traffic Distribution');
        $response->assertViewHas('totalCapacity', 100);
        $response->assertViewHas('attendedCount', 1);
        $response->assertViewHas('totalRevenue', 75.00);
    }

    public function test_organizer_cannot_view_detailed_report_for_another_organizers_event(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();

        $event2 = Event::factory()->published()->create(['organizer_id' => $organizer2->id]);

        $response = $this->actingAs($organizer1)->get(route('organizer.events.reports.show', $event2));

        $response->assertStatus(403);
    }

    public function test_admin_can_view_detailed_report_for_any_event(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($admin)->get(route('organizer.events.reports.show', $event));

        $response->assertStatus(200);
        $response->assertSee($event->title);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. CSV EXPORTS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_can_export_event_attendees_csv(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'slug' => 'tech-expo-2026',
        ]);
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Gold Pass',
            'price' => 199.00,
        ]);
        $attendee = User::factory()->participant()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@navy.mil',
        ]);
        Registration::factory()->create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'registration_code' => 'EVENT-REG-GRACE001',
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.reports.export-attendees', $event));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('event-tech-expo-2026-attendees-', (string) $response->headers->get('Content-Disposition'));

        // Stream and verify CSV contents
        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('Registration Code', $csvContent);
        $this->assertStringContainsString('Attendee Name', $csvContent);
        $this->assertStringContainsString('EVENT-REG-GRACE001', $csvContent);
        $this->assertStringContainsString('Grace Hopper', $csvContent);
        $this->assertStringContainsString('grace@navy.mil', $csvContent);
        $this->assertStringContainsString('Gold Pass', $csvContent);
        $this->assertStringContainsString('199.00', $csvContent);
    }

    public function test_attendees_csv_escapes_formula_characters_to_prevent_injection(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        // Malicious participant name designed to trigger CSV injection
        $attacker = User::factory()->participant()->create([
            'name' => '=cmd|"/C calc"!A0',
        ]);
        Registration::factory()->create([
            'user_id' => $attacker->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.reports.export-attendees', $event));

        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        // Must be prefixed with a single quote to neutralize formula
        $this->assertStringContainsString("'=cmd", $csvContent);
    }

    public function test_organizer_cannot_export_another_organizers_attendees_csv(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $event2 = Event::factory()->published()->create(['organizer_id' => $organizer2->id]);

        $response = $this->actingAs($organizer1)->get(route('organizer.events.reports.export-attendees', $event2));

        $response->assertStatus(403);
    }

    public function test_organizer_can_export_events_summary_csv(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Annual Gala 2026',
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'price' => 80.00, 'quota' => 50]);
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('events-summary-report-', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('Event Title', $csvContent);
        $this->assertStringContainsString('Total Revenue', $csvContent);
        $this->assertStringContainsString('Annual Gala 2026', $csvContent);
        $this->assertStringContainsString('80.00', $csvContent);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. EMPTY STATE
    // ──────────────────────────────────────────────────────────────────────────

    public function test_reports_empty_state_renders_cleanly_when_organizer_has_no_events(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('No events found');
        $response->assertViewHas('totalEvents', 0);
        $response->assertViewHas('totalCapacity', 0);
        $response->assertViewHas('totalRegistrations', 0);
        $response->assertViewHas('totalAttended', 0);
        $response->assertViewHas('overallAttendanceRate', 0);
        $response->assertViewHas('totalRevenue', 0.0);
    }
}
