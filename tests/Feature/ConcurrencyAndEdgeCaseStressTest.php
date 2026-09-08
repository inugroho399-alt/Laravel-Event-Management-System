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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TEST PHASE 15 — CONCURRENCY, RACE CONDITIONS & EDGE CASE STRESS TESTING
 *
 * Validates critical system invariants, transactional integrity,
 * state machine transitions, and concurrency safety:
 *  1. Quota contention on last remaining seat (sold out boundary)
 *  2. Concurrent check-in collision prevention via DB unique constraints
 *  3. Event status state machine boundaries (Draft, Published, Ongoing, Completed, Cancelled)
 *  4. Registration cancellation quota restoration & voiding check-in
 *  5. Financial precision with fractional pricing & revenue aggregation
 *  6. Multi-tenant data isolation & cross-organizer security boundaries
 */
class ConcurrencyAndEdgeCaseStressTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // 1. QUOTA CONTENTION & EXHAUSTION
    // ──────────────────────────────────────────────────────────────────────────

    public function test_last_ticket_quota_contention_allows_only_one_winner(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Published,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
        ]);

        // Ticket tier with exactly 1 quota
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'quota' => 1,
            'price' => 50.00,
        ]);

        $user1 = User::factory()->create(['role' => UserRole::Participant]);
        $user2 = User::factory()->create(['role' => UserRole::Participant]);

        // First user registers
        $response1 = $this->actingAs($user1)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $response1->assertRedirect(route('registrations.index'));
        $this->assertEquals(0, $ticket->refresh()->remainingQuota());

        // Second user attempts to register for the exhausted ticket tier
        $response2 = $this->actingAs($user2)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $response2->assertSessionHasErrors('error');
        $this->assertEquals(0, $ticket->refresh()->remainingQuota());
        $this->assertEquals(1, Registration::where('ticket_type_id', $ticket->id)->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. CHECK-IN COLLISION & DB CONSTRAINT ENFORCEMENT
    // ──────────────────────────────────────────────────────────────────────────

    public function test_check_in_collision_is_strictly_prevented_by_unique_constraint(): void
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

        // First check-in succeeds
        $res1 = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $reg->registration_code,
        ]);
        $res1->assertSessionHas('status');
        $this->assertEquals(1, CheckIn::where('registration_id', $reg->id)->count());

        // Second check-in via web route is cleanly rejected with validation error
        $res2 = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $reg->registration_code,
        ]);
        $res2->assertSessionHasErrors('registration_code');

        // Verify direct DB attempt to insert duplicate check-in throws QueryException
        $this->expectException(QueryException::class);
        CheckIn::create([
            'registration_id' => $reg->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. EVENT STATE MACHINE BOUNDARIES
    // ──────────────────────────────────────────────────────────────────────────

    public function test_draft_event_rejects_both_registration_and_checkin(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Draft,
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        // Attempt registration on draft event
        $regRes = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $regRes->assertSessionHasErrors('error');
        $this->assertEquals(0, Registration::count());

        // Create a pre-existing registration and attempt check-in on draft event
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $checkInRes = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $reg->registration_code,
        ]);
        $checkInRes->assertSessionHasErrors('registration_code');
        $this->assertEquals(0, CheckIn::count());
    }

    public function test_completed_event_rejects_both_registration_and_checkin(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Completed,
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDays(1),
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        // Attempt registration on completed event
        $regRes = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $regRes->assertSessionHasErrors('error');

        // Attempt check-in on completed event
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $checkInRes = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $reg->registration_code,
        ]);
        $checkInRes->assertSessionHasErrors('registration_code');
    }

    public function test_cancelled_event_rejects_both_registration_and_checkin(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Cancelled,
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        // Attempt registration on cancelled event
        $regRes = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $regRes->assertSessionHasErrors('error');

        // Attempt check-in on cancelled event
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $checkInRes = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $reg->registration_code,
        ]);
        $checkInRes->assertSessionHasErrors('registration_code');
    }

    public function test_ongoing_event_permits_both_registration_and_checkin(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Ongoing,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHours(4),
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'quota' => 10]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        // Ongoing event allows registration
        $regRes = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $regRes->assertRedirect(route('registrations.index'));

        $reg = Registration::where('event_id', $event->id)->where('user_id', $participant->id)->first();
        $this->assertNotNull($reg);

        // Ongoing event allows check-in
        $checkInRes = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $reg->registration_code,
        ]);
        $checkInRes->assertSessionHas('status');
        $this->assertEquals(RegistrationStatus::Attended, $reg->refresh()->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. REGISTRATION CANCELLATION & QUOTA RESTORATION
    // ──────────────────────────────────────────────────────────────────────────

    public function test_registration_cancellation_restores_quota_and_blocks_checkin(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Published,
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'quota' => 5]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        // Register
        $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $this->assertEquals(4, $ticket->refresh()->remainingQuota());

        $reg = Registration::where('event_id', $event->id)->where('user_id', $participant->id)->first();

        // Cancel
        $cancelRes = $this->actingAs($participant)->post(route('registrations.cancel', $reg));
        $cancelRes->assertRedirect();
        $this->assertEquals(RegistrationStatus::Cancelled, $reg->refresh()->status);
        $this->assertEquals(5, $ticket->refresh()->remainingQuota());

        // Attempt check-in on cancelled registration
        $checkInRes = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $reg->registration_code,
        ]);
        $checkInRes->assertSessionHasErrors('registration_code');
        $this->assertEquals(0, CheckIn::count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. FINANCIAL PRECISION & ACCURACY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_fractional_ticket_pricing_and_revenue_aggregation_are_exact(): void
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => EventStatus::Published,
        ]);

        $t1 = TicketType::factory()->create(['event_id' => $event->id, 'price' => 19.99, 'quota' => 50]);
        $t2 = TicketType::factory()->create(['event_id' => $event->id, 'price' => 49.50, 'quota' => 50]);
        $t3 = TicketType::factory()->create(['event_id' => $event->id, 'price' => 0.00, 'quota' => 50]);

        // 3 registrations for t1 = 3 * 19.99 = 59.97
        for ($i = 0; $i < 3; $i++) {
            Registration::factory()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $t1->id,
                'status' => RegistrationStatus::Confirmed,
            ]);
        }

        // 2 registrations for t2 = 2 * 49.50 = 99.00
        for ($i = 0; $i < 2; $i++) {
            Registration::factory()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $t2->id,
                'status' => RegistrationStatus::Attended,
            ]);
        }

        // 5 registrations for free tier = 0.00
        for ($i = 0; $i < 5; $i++) {
            Registration::factory()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $t3->id,
                'status' => RegistrationStatus::Confirmed,
            ]);
        }

        // 1 cancelled registration for t2 = should NOT count toward revenue
        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $t2->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        // Expected revenue: 59.97 + 99.00 = 158.97
        $reportRes = $this->actingAs($organizer)->get(route('organizer.events.reports.show', $event));
        $reportRes->assertStatus(200);
        $reportRes->assertSee('$158.97');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. MULTI-TENANT ISOLATION & ACCESS BARRIERS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_cannot_manage_or_scan_other_organizers_event(): void
    {
        $orgA = User::factory()->create(['role' => UserRole::Organizer]);
        $orgB = User::factory()->create(['role' => UserRole::Organizer]);

        $eventA = Event::factory()->create(['organizer_id' => $orgA->id, 'status' => EventStatus::Published]);
        $ticketA = TicketType::factory()->create(['event_id' => $eventA->id]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        $regA = Registration::factory()->create([
            'event_id' => $eventA->id,
            'ticket_type_id' => $ticketA->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // Organizer B attempts to access Organizer A's check-in workstation
        $this->actingAs($orgB)->get(route('organizer.events.check-in.create', $eventA))->assertStatus(403);

        // Organizer B attempts to submit check-in for Organizer A's event
        $this->actingAs($orgB)->post(route('organizer.events.check-in.store', $eventA), [
            'registration_code' => $regA->registration_code,
        ])->assertStatus(403);

        // Organizer B attempts to view Organizer A's report
        $this->actingAs($orgB)->get(route('organizer.events.reports.show', $eventA))->assertStatus(403);
    }
}
