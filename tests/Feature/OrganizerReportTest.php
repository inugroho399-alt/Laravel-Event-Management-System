<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * TEST PHASE 12 — REPORTING
 *
 * Verifies:
 * 1. Reports use real database data (dynamically asserted against DB queries).
 * 2. Registration counts are accurate (confirmed vs attended vs cancelled).
 * 3. Check-in counts are accurate (matches CheckIn records).
 * 4. Ticket statistics are accurate (quota, sold, remaining, revenue).
 * 5. Event statistics are accurate (capacity, registrations, attended, rate, revenue).
 * 6. Organizer only sees authorized event data (strict multi-tenant isolation).
 * 7. Admin can access appropriate system-wide reports.
 * 8. Empty datasets are handled correctly (zero events, zero tickets, division-by-zero safety).
 * 9. Filters work correctly (status, event_id).
 * 10. Date ranges work correctly (boundary testing for date_from & date_to).
 * 11. No duplicate records are counted incorrectly (joins with multiple tickets/registrations).
 */
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
    // 2. REAL DATABASE DATA & COMPARISON
    // ──────────────────────────────────────────────────────────────────────────

    public function test_reports_compare_directly_against_real_database_records(): void
    {
        $organizer = User::factory()->organizer()->create();

        // Create 2 events for this organizer
        $event1 = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $event2 = Event::factory()->ongoing()->create(['organizer_id' => $organizer->id]);

        $t1 = TicketType::factory()->create(['event_id' => $event1->id, 'price' => 75.00, 'quota' => 40]);
        $t2 = TicketType::factory()->create(['event_id' => $event1->id, 'price' => 25.00, 'quota' => 60]);
        $t3 = TicketType::factory()->create(['event_id' => $event2->id, 'price' => 120.00, 'quota' => 50]);

        // Event 1 registrations: 2 confirmed, 1 attended
        Registration::factory()->create([
            'event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        Registration::factory()->create([
            'event_id' => $event1->id, 'ticket_type_id' => $t2->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        $attended1 = Registration::factory()->create([
            'event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Attended,
        ]);
        CheckIn::create([
            'registration_id' => $attended1->id, 'checked_in_by' => $organizer->id, 'checked_in_at' => now(),
        ]);

        // Event 2 registrations: 1 attended, 1 cancelled
        $attended2 = Registration::factory()->create([
            'event_id' => $event2->id, 'ticket_type_id' => $t3->id, 'status' => RegistrationStatus::Attended,
        ]);
        CheckIn::create([
            'registration_id' => $attended2->id, 'checked_in_by' => $organizer->id, 'checked_in_at' => now(),
        ]);
        Registration::factory()->create([
            'event_id' => $event2->id, 'ticket_type_id' => $t3->id, 'status' => RegistrationStatus::Cancelled,
        ]);

        // Query database directly to compute ground-truth expectations (NO hardcoding)
        $eventIds = Event::where('organizer_id', $organizer->id)->pluck('id');
        $expectedTotalEvents = $eventIds->count();
        $expectedTotalCapacity = TicketType::whereIn('event_id', $eventIds)->sum('quota');
        $expectedActiveRegistrations = Registration::whereIn('event_id', $eventIds)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->count();
        $expectedTotalAttended = Registration::whereIn('event_id', $eventIds)
            ->where('status', RegistrationStatus::Attended)
            ->count();
        $expectedRevenue = (float) Registration::whereIn('registrations.event_id', $eventIds)
            ->whereIn('registrations.status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Attended->value])
            ->join('ticket_types', 'registrations.ticket_type_id', '=', 'ticket_types.id')
            ->sum('ticket_types.price');
        $expectedAttendanceRate = round(($expectedTotalAttended / $expectedActiveRegistrations) * 100, 1);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));

        $response->assertStatus(200);
        $response->assertViewHas('totalEvents', $expectedTotalEvents);
        $response->assertViewHas('totalCapacity', $expectedTotalCapacity);
        $response->assertViewHas('totalRegistrations', $expectedActiveRegistrations);
        $response->assertViewHas('totalAttended', $expectedTotalAttended);
        $response->assertViewHas('totalRevenue', $expectedRevenue);
        $response->assertViewHas('overallAttendanceRate', $expectedAttendanceRate);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. REGISTRATION COUNTS ACCURACY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_registration_counts_are_accurate_and_exclude_cancelled_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'price' => 50.00, 'quota' => 20]);

        // 3 Confirmed
        Registration::factory()->count(3)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        // 2 Attended
        $attendedRegs = Registration::factory()->count(2)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id, 'status' => RegistrationStatus::Attended,
        ]);
        foreach ($attendedRegs as $reg) {
            CheckIn::create([
                'registration_id' => $reg->id, 'checked_in_by' => $organizer->id, 'checked_in_at' => now(),
            ]);
        }
        // 4 Cancelled (must NOT be counted in active registrations or revenue)
        Registration::factory()->count(4)->create([
            'event_id' => $event->id, 'ticket_type_id' => $ticket->id, 'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));

        $response->assertStatus(200);
        // Active registrations: exactly 3 confirmed + 2 attended = 5
        $response->assertViewHas('totalRegistrations', 5);
        // Attended: exactly 2
        $response->assertViewHas('totalAttended', 2);
        // Revenue: 5 active × $50 = $250.00 (NOT 9 × 50 = 450)
        $response->assertViewHas('totalRevenue', 250.00);

        // Also verify show report for the event
        $showResponse = $this->actingAs($organizer)->get(route('organizer.events.reports.show', $event));
        $showResponse->assertStatus(200);
        $showResponse->assertViewHas('confirmedCount', 3);
        $showResponse->assertViewHas('attendedCount', 2);
        $showResponse->assertViewHas('cancelledCount', 4);
        $showResponse->assertViewHas('activeRegistrationsCount', 5);
        $showResponse->assertViewHas('totalRegistrationsCount', 9);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. CHECK-IN COUNTS ACCURACY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_check_in_counts_are_accurate_and_match_checkin_records(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $checkedInCount = 4;
        $unCheckedCount = 3;

        // Create checked-in attendees
        for ($i = 0; $i < $checkedInCount; $i++) {
            $reg = Registration::factory()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticket->id,
                'status' => RegistrationStatus::Attended,
            ]);
            CheckIn::create([
                'registration_id' => $reg->id,
                'checked_in_by' => $organizer->id,
                'checked_in_at' => now()->subMinutes(10 * $i),
            ]);
        }

        // Create pending (not checked in) attendees
        Registration::factory()->count($unCheckedCount)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // Verify CheckIn table count
        $dbCheckInCount = CheckIn::whereHas('registration', fn ($q) => $q->where('event_id', $event->id))->count();
        $this->assertSame($checkedInCount, $dbCheckInCount);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));
        $response->assertStatus(200);
        $response->assertViewHas('totalAttended', $dbCheckInCount);

        $showResponse = $this->actingAs($organizer)->get(route('organizer.events.reports.show', $event));
        $showResponse->assertStatus(200);
        $showResponse->assertViewHas('attendedCount', $dbCheckInCount);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. TICKET STATISTICS ACCURACY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_ticket_statistics_are_accurate_for_multiple_tiers(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $vip = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'VIP Lounge',
            'price' => 200.00,
            'quota' => 25,
        ]);
        $regular = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Standard Access',
            'price' => 50.00,
            'quota' => 100,
        ]);

        // 10 VIP registrations (8 attended, 2 confirmed)
        $vipRegs = Registration::factory()->count(8)->create([
            'event_id' => $event->id, 'ticket_type_id' => $vip->id, 'status' => RegistrationStatus::Attended,
        ]);
        foreach ($vipRegs as $r) {
            CheckIn::create(['registration_id' => $r->id, 'checked_in_by' => $organizer->id, 'checked_in_at' => now()]);
        }
        Registration::factory()->count(2)->create([
            'event_id' => $event->id, 'ticket_type_id' => $vip->id, 'status' => RegistrationStatus::Confirmed,
        ]);

        // 30 Regular registrations (15 attended, 15 confirmed)
        $regRegs = Registration::factory()->count(15)->create([
            'event_id' => $event->id, 'ticket_type_id' => $regular->id, 'status' => RegistrationStatus::Attended,
        ]);
        foreach ($regRegs as $r) {
            CheckIn::create(['registration_id' => $r->id, 'checked_in_by' => $organizer->id, 'checked_in_at' => now()]);
        }
        Registration::factory()->count(15)->create([
            'event_id' => $event->id, 'ticket_type_id' => $regular->id, 'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.reports.show', $event));

        $response->assertStatus(200);

        /** @var Collection $tiers */
        $tiers = $response->viewData('ticketTiers');

        $vipTier = $tiers->firstWhere('name', 'VIP Lounge');
        $this->assertNotNull($vipTier);
        $this->assertSame(25, $vipTier->quota);
        $this->assertSame(10, $vipTier->sold);
        $this->assertSame(15, $vipTier->remaining);
        $this->assertSame(8, $vipTier->attended);
        $this->assertSame(80.0, $vipTier->attendance_rate); // 8/10 = 80%
        $this->assertSame(2000.0, $vipTier->revenue); // 10 × 200 = 2000

        $regularTier = $tiers->firstWhere('name', 'Standard Access');
        $this->assertNotNull($regularTier);
        $this->assertSame(100, $regularTier->quota);
        $this->assertSame(30, $regularTier->sold);
        $this->assertSame(70, $regularTier->remaining);
        $this->assertSame(15, $regularTier->attended);
        $this->assertSame(50.0, $regularTier->attendance_rate); // 15/30 = 50%
        $this->assertSame(1500.0, $regularTier->revenue); // 30 × 50 = 1500
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. EVENT STATISTICS ACCURACY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_event_statistics_are_accurate_across_multiple_events(): void
    {
        $organizer = User::factory()->organizer()->create();

        $event1 = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $event2 = Event::factory()->completed()->create(['organizer_id' => $organizer->id]);

        $t1 = TicketType::factory()->create(['event_id' => $event1->id, 'quota' => 50, 'price' => 10.00]);
        $t2 = TicketType::factory()->create(['event_id' => $event2->id, 'quota' => 50, 'price' => 20.00]);

        // Event 1: 10 registrations (5 attended) -> attendance rate 50%
        $e1Attended = Registration::factory()->count(5)->create([
            'event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Attended,
        ]);
        foreach ($e1Attended as $r) {
            CheckIn::create(['registration_id' => $r->id, 'checked_in_by' => $organizer->id, 'checked_in_at' => now()]);
        }
        Registration::factory()->count(5)->create([
            'event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Confirmed,
        ]);

        // Event 2: 20 registrations (20 attended) -> attendance rate 100%
        $e2Attended = Registration::factory()->count(20)->create([
            'event_id' => $event2->id, 'ticket_type_id' => $t2->id, 'status' => RegistrationStatus::Attended,
        ]);
        foreach ($e2Attended as $r) {
            CheckIn::create(['registration_id' => $r->id, 'checked_in_by' => $organizer->id, 'checked_in_at' => now()]);
        }

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));

        $response->assertStatus(200);

        /** @var LengthAwarePaginator $events */
        $events = $response->viewData('events');

        $ev1 = $events->firstWhere('id', $event1->id);
        $this->assertSame(50, $ev1->total_capacity);
        $this->assertSame(10, $ev1->active_registrations_count);
        $this->assertSame(5, $ev1->attended_count);
        $this->assertSame(50.0, $ev1->attendance_rate);
        $this->assertSame(100.0, $ev1->revenue); // 10 × 10 = 100

        $ev2 = $events->firstWhere('id', $event2->id);
        $this->assertSame(50, $ev2->total_capacity);
        $this->assertSame(20, $ev2->active_registrations_count);
        $this->assertSame(20, $ev2->attended_count);
        $this->assertSame(100.0, $ev2->attendance_rate);
        $this->assertSame(400.0, $ev2->revenue); // 20 × 20 = 400
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. AUTHORIZATION & MULTI-TENANT ISOLATION
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_only_sees_authorized_event_data(): void
    {
        $organizer1 = User::factory()->organizer()->create(['name' => 'First Organizer']);
        $organizer2 = User::factory()->organizer()->create(['name' => 'Second Organizer']);

        $event1 = Event::factory()->published()->create([
            'organizer_id' => $organizer1->id,
            'title' => 'First Organizer Conference',
        ]);
        $t1 = TicketType::factory()->create(['event_id' => $event1->id, 'price' => 100.00, 'quota' => 50]);
        Registration::factory()->count(2)->create([
            'event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Confirmed,
        ]);

        $event2 = Event::factory()->published()->create([
            'organizer_id' => $organizer2->id,
            'title' => 'Confidential Corporate Secret Summit',
        ]);
        $t2 = TicketType::factory()->create(['event_id' => $event2->id, 'price' => 999.00, 'quota' => 10]);
        Registration::factory()->count(5)->create([
            'event_id' => $event2->id, 'ticket_type_id' => $t2->id, 'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer1)->get(route('organizer.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('First Organizer Conference');
        $response->assertDontSee('Confidential Corporate Secret Summit');

        $response->assertViewHas('totalEvents', 1);
        $response->assertViewHas('totalRevenue', 200.00); // 2 × 100
        $response->assertViewHas('totalRegistrations', 2);

        // Cannot view detailed report of another organizer's event
        $forbiddenResponse = $this->actingAs($organizer1)->get(route('organizer.events.reports.show', $event2));
        $forbiddenResponse->assertStatus(403);

        // Cannot export attendees CSV of another organizer's event
        $exportResponse = $this->actingAs($organizer1)->get(route('organizer.events.reports.export-attendees', $event2));
        $exportResponse->assertStatus(403);
    }

    public function test_admin_can_access_appropriate_system_wide_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $org1 = User::factory()->organizer()->create();
        $org2 = User::factory()->organizer()->create();

        $event1 = Event::factory()->published()->create(['organizer_id' => $org1->id, 'title' => 'Org 1 Gala']);
        $event2 = Event::factory()->published()->create(['organizer_id' => $org2->id, 'title' => 'Org 2 Expo']);

        $t1 = TicketType::factory()->create(['event_id' => $event1->id, 'quota' => 40, 'price' => 50.00]);
        $t2 = TicketType::factory()->create(['event_id' => $event2->id, 'quota' => 60, 'price' => 100.00]);

        Registration::factory()->create(['event_id' => $event1->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Confirmed]);
        Registration::factory()->create(['event_id' => $event2->id, 'ticket_type_id' => $t2->id, 'status' => RegistrationStatus::Confirmed]);

        // Admin index sees both events
        $indexResponse = $this->actingAs($admin)->get(route('organizer.reports.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertViewHas('totalEvents', 2);
        $indexResponse->assertViewHas('totalCapacity', 100);
        $indexResponse->assertViewHas('totalRegistrations', 2);
        $indexResponse->assertViewHas('totalRevenue', 150.00);

        // Admin can view detailed report of either event
        $showResponse = $this->actingAs($admin)->get(route('organizer.events.reports.show', $event1));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Org 1 Gala');

        // Admin summary export includes both events
        $summaryExport = $this->actingAs($admin)->get(route('organizer.reports.export'));
        $summaryExport->assertStatus(200);

        ob_start();
        $summaryExport->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('Org 1 Gala', $csvContent);
        $this->assertStringContainsString('Org 2 Expo', $csvContent);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. EMPTY DATASETS & DIVISION BY ZERO SAFETY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_empty_datasets_are_handled_correctly_without_division_by_zero(): void
    {
        $organizer = User::factory()->organizer()->create();

        // 1. Completely empty organizer (0 events)
        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));
        $response->assertStatus(200);
        $response->assertSee('No events found');
        $response->assertViewHas('totalEvents', 0);
        $response->assertViewHas('totalCapacity', 0);
        $response->assertViewHas('totalRegistrations', 0);
        $response->assertViewHas('totalAttended', 0);
        $response->assertViewHas('overallAttendanceRate', 0);
        $response->assertViewHas('capacityUtilization', 0);
        $response->assertViewHas('totalRevenue', 0.0);

        // 2. Event exists but has 0 ticket types, 0 registrations
        $emptyEvent = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $showResponse = $this->actingAs($organizer)->get(route('organizer.events.reports.show', $emptyEvent));
        $showResponse->assertStatus(200);
        $showResponse->assertViewHas('totalCapacity', 0);
        $showResponse->assertViewHas('activeRegistrationsCount', 0);
        $showResponse->assertViewHas('attendedCount', 0);
        $showResponse->assertViewHas('attendanceRate', 0);
        $showResponse->assertViewHas('capacityUtilization', 0);
        $showResponse->assertViewHas('totalRevenue', 0.0);

        // 3. Event with tickets has 0 registrations (turnout rate 0%)
        TicketType::factory()->create(['event_id' => $emptyEvent->id, 'quota' => 100, 'price' => 50.00]);
        $showResponse2 = $this->actingAs($organizer)->get(route('organizer.events.reports.show', $emptyEvent));
        $showResponse2->assertStatus(200);
        $showResponse2->assertViewHas('totalCapacity', 100);
        $showResponse2->assertViewHas('activeRegistrationsCount', 0);
        $showResponse2->assertViewHas('attendanceRate', 0);
        $showResponse2->assertViewHas('capacityUtilization', 0);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. FILTERS (STATUS, EVENT_ID)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_status_filters_work_correctly(): void
    {
        $organizer = User::factory()->organizer()->create();

        $published = Event::factory()->published()->create(['organizer_id' => $organizer->id, 'title' => 'Live Now']);
        $draft = Event::factory()->draft()->create(['organizer_id' => $organizer->id, 'title' => 'Draft In Progress']);
        $cancelled = Event::factory()->cancelled()->create(['organizer_id' => $organizer->id, 'title' => 'Terminated Event']);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index', [
            'status' => EventStatus::Published->value,
        ]));

        $response->assertStatus(200);
        $events = $response->viewData('events');
        $this->assertTrue($events->pluck('title')->contains('Live Now'));
        $this->assertFalse($events->pluck('title')->contains('Draft In Progress'));
        $this->assertFalse($events->pluck('title')->contains('Terminated Event'));
        $response->assertViewHas('totalEvents', 1);
    }

    public function test_event_dropdown_filter_works_correctly(): void
    {
        $organizer = User::factory()->organizer()->create();

        $targetEvent = Event::factory()->published()->create(['organizer_id' => $organizer->id, 'title' => 'Chosen Event']);
        $otherEvent = Event::factory()->published()->create(['organizer_id' => $organizer->id, 'title' => 'Other Event']);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index', [
            'event_id' => $targetEvent->id,
        ]));

        $response->assertStatus(200);
        $events = $response->viewData('events');
        $this->assertTrue($events->pluck('title')->contains('Chosen Event'));
        $this->assertFalse($events->pluck('title')->contains('Other Event'));
        $response->assertViewHas('totalEvents', 1);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. DATE RANGE BOUNDARIES
    // ──────────────────────────────────────────────────────────────────────────

    public function test_date_ranges_boundary_inclusions_work_correctly(): void
    {
        $organizer = User::factory()->organizer()->create();

        $rangeStart = Carbon::parse('2026-06-01');
        $rangeEnd = Carbon::parse('2026-06-30');

        // Exactly at start of range (00:00:00 on June 1)
        $eventAtStart = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Event at Start Boundary',
            'start_date' => Carbon::parse('2026-06-01 00:00:00'),
        ]);

        // Mid-range (June 15)
        $eventMid = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Event Mid Range',
            'start_date' => Carbon::parse('2026-06-15 14:00:00'),
        ]);

        // Exactly at end boundary (23:59:00 on June 30)
        $eventAtEnd = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Event at End Boundary',
            'start_date' => Carbon::parse('2026-06-30 23:59:00'),
        ]);

        // Before range (May 31 23:59:59)
        $eventBefore = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Event Before Range',
            'start_date' => Carbon::parse('2026-05-31 23:59:59'),
        ]);

        // After range (July 1 00:00:01)
        $eventAfter = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Event After Range',
            'start_date' => Carbon::parse('2026-07-01 00:00:01'),
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $response->assertStatus(200);
        $events = $response->viewData('events');
        $this->assertTrue($events->pluck('title')->contains('Event at Start Boundary'));
        $this->assertTrue($events->pluck('title')->contains('Event Mid Range'));
        $this->assertTrue($events->pluck('title')->contains('Event at End Boundary'));
        $this->assertFalse($events->pluck('title')->contains('Event Before Range'));
        $this->assertFalse($events->pluck('title')->contains('Event After Range'));
        $response->assertViewHas('totalEvents', 3);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11. NO DUPLICATE RECORDS ARE COUNTED INCORRECTLY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_no_duplicate_records_counted_with_multiple_ticket_types_and_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();

        // 1 event with 3 distinct ticket types
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $t1 = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'Bronze', 'price' => 10.00, 'quota' => 10]);
        $t2 = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'Silver', 'price' => 20.00, 'quota' => 10]);
        $t3 = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'Gold', 'price' => 30.00, 'quota' => 10]);

        $user1 = User::factory()->participant()->create();
        $user2 = User::factory()->participant()->create();

        // user1 registers for Bronze and Gold
        Registration::factory()->create([
            'user_id' => $user1->id, 'event_id' => $event->id, 'ticket_type_id' => $t1->id, 'status' => RegistrationStatus::Confirmed,
        ]);
        $attendedReg = Registration::factory()->create([
            'user_id' => $user1->id, 'event_id' => $event->id, 'ticket_type_id' => $t3->id, 'status' => RegistrationStatus::Attended,
        ]);
        CheckIn::create(['registration_id' => $attendedReg->id, 'checked_in_by' => $organizer->id, 'checked_in_at' => now()]);

        // user2 registers for Silver
        Registration::factory()->create([
            'user_id' => $user2->id, 'event_id' => $event->id, 'ticket_type_id' => $t2->id, 'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.reports.index'));

        $response->assertStatus(200);

        // Event count must be exactly 1, NOT 3 (multiplied by ticket count)
        $response->assertViewHas('totalEvents', 1);

        // Capacity: 10 + 10 + 10 = 30
        $response->assertViewHas('totalCapacity', 30);

        // Total active registrations must be exactly 3, NOT duplicated
        $response->assertViewHas('totalRegistrations', 3);

        // Attended must be exactly 1
        $response->assertViewHas('totalAttended', 1);

        // Total revenue must be exactly 10 + 30 + 20 = $60.00
        $response->assertViewHas('totalRevenue', 60.00);

        // In per-event table, event count is 1 and its revenue is 60
        $eventItem = $response->viewData('events')->first();
        $this->assertSame(1, $response->viewData('events')->count());
        $this->assertSame(60.0, $eventItem->revenue);
        $this->assertSame(3, $eventItem->active_registrations_count);
        $this->assertSame(1, $eventItem->attended_count);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12. CSV EXPORT & FORMULA INJECTION PREVENTION
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
}
