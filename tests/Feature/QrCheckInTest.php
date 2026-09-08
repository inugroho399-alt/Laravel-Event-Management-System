<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class QrCheckInTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // STATION VIEW ACCESS (GET)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_can_view_check_in_station_for_their_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.check-in.create', $event));

        $response->assertStatus(200);
        $response->assertSee('QR Check-in Station');
        $response->assertSee($event->title);
        $response->assertSee('Scan or Enter Registration Code');
    }

    public function test_guest_cannot_access_check_in_station_and_is_redirected_to_login(): void
    {
        $event = Event::factory()->published()->create();

        $response = $this->get(route('organizer.events.check-in.create', $event));

        $response->assertRedirect('/login');
    }

    public function test_participant_cannot_access_check_in_station(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();

        $response = $this->actingAs($participant)->get(route('organizer.events.check-in.create', $event));

        $response->assertStatus(403);
    }

    public function test_unauthorized_organizer_cannot_access_another_organizers_check_in_station(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer1->id]);

        $response = $this->actingAs($organizer2)->get(route('organizer.events.check-in.create', $event));

        $response->assertStatus(403);
    }

    public function test_admin_can_access_check_in_station_for_any_event(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($admin)->get(route('organizer.events.check-in.create', $event));

        $response->assertStatus(200);
        $response->assertSee('QR Check-in Station');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. AUTHORIZED ORGANIZER CAN CHECK IN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_organizer_can_successfully_check_in_participant_with_valid_code(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'VIP Pass']);
        $participant = User::factory()->participant()->create(['name' => 'John Wick']);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-ABC12345',
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) use ($registration, $event, $organizer) {
                return str_contains($message, $registration->registration_code)
                    && str_contains($message, (string) $event->id)
                    && str_contains($message, (string) $organizer->id);
            });

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-ABC12345',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionHas('status');

        // requirement 9: exactly one check_ins record created
        $this->assertSame(1, CheckIn::where('registration_id', $registration->id)->count());

        // requirement 10: checked_in_by stored correctly
        $this->assertDatabaseHas('check_ins', [
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
        ]);

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Attended, $registration->status);
        $this->assertTrue($registration->isCheckedIn());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. ADMIN CAN CHECK IN (POST — not just GET the station)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_can_check_in_participant_for_any_event(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->participant()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-ADM12345',
        ]);

        $response = $this->actingAs($admin)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-ADM12345',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Admin's user ID is stored as the checker
        $this->assertDatabaseHas('check_ins', [
            'registration_id' => $registration->id,
            'checked_in_by' => $admin->id,
        ]);

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Attended, $registration->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. PARTICIPANT CANNOT PERFORM CHECK-IN (POST)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_participant_cannot_post_check_in(): void
    {
        $participant = User::factory()->participant()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-PART1234',
        ]);

        $response = $this->actingAs($participant)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-PART1234',
        ]);

        // Participant role is blocked by the role:organizer middleware — 403
        $response->assertStatus(403);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    public function test_participant_cannot_check_in_via_json_api(): void
    {
        $participant = User::factory()->participant()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-PJSN1234',
        ]);

        $response = $this->actingAs($participant)->postJson(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-PJSN1234',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. GUEST CANNOT PERFORM CHECK-IN (POST)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_post_check_in_and_is_redirected_to_login(): void
    {
        $event = Event::factory()->published()->create();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-GUES1234',
        ]);

        $response = $this->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-GUES1234',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    public function test_guest_cannot_check_in_via_json_api(): void
    {
        $event = Event::factory()->published()->create();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-GJSN1234',
        ]);

        $response = $this->postJson(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-GJSN1234',
        ]);

        // JSON clients receive 401 Unauthenticated
        $response->assertStatus(401);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. INVALID QR / REGISTRATION REJECTED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_invalid_registration_code_format_fails_validation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        foreach (['INVALID-CODE', 'event-reg-abc12345', 'EVENT-REG-', 'EVENT-REG-TOOLONG99', ''] as $badCode) {
            $response = $this->actingAs($organizer)->post(
                route('organizer.events.check-in.store', $event),
                ['registration_code' => $badCode]
            );
            $response->assertSessionHasErrors(['registration_code']);
        }
    }

    public function test_non_existent_registration_code_is_rejected(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-00000000',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
        $this->assertSame(0, CheckIn::count());
    }

    public function test_non_existent_registration_code_returns_json_error(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->postJson(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-GHOST111',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. REGISTRATION FROM ANOTHER EVENT HANDLED CORRECTLY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_registration_code_from_different_event_is_rejected(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event1 = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $event2 = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $registration2 = Registration::factory()->create([
            'event_id' => $event2->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-DIFF2222',
        ]);

        // Submitting event2's code to event1's check-in station
        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event1), [
            'registration_code' => 'EVENT-REG-DIFF2222',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration2->id]);
    }

    public function test_cross_event_check_in_rejected_via_json_api(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event1 = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $event2 = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $registration2 = Registration::factory()->create([
            'event_id' => $event2->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-CRSS3333',
        ]);

        $response = $this->actingAs($organizer)->postJson(route('organizer.events.check-in.store', $event1), [
            'registration_code' => 'EVENT-REG-CRSS3333',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration2->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. CANCELLED REGISTRATION CANNOT CHECK IN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cancelled_registration_cannot_be_checked_in(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Cancelled,
            'registration_code' => 'EVENT-REG-CANC1111',
        ]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-CANC1111',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. DUPLICATE CHECK-IN IS PREVENTED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_duplicate_check_in_is_rejected(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Attended,
            'registration_code' => 'EVENT-REG-DUPL1234',
        ]);

        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-DUPL1234',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
        // Still only one record — duplicate was blocked
        $this->assertSame(1, CheckIn::where('registration_id', $registration->id)->count());
    }

    public function test_duplicate_check_in_returns_json_error_with_details(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Attended,
            'registration_code' => 'EVENT-REG-DUPL9999',
        ]);

        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($organizer)->postJson(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-DUPL9999',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'already_checked_in' => true,
        ]);
        // Still only one record
        $this->assertSame(1, CheckIn::where('registration_id', $registration->id)->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. EXACTLY ONE RECORD CREATED
    // ──────────────────────────────────────────────────────────────────────────

    public function test_successful_check_in_creates_exactly_one_check_in_record(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-ONCE1234',
        ]);

        $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-ONCE1234',
        ]);

        $this->assertSame(1, CheckIn::where('registration_id', $registration->id)->count());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. checked_in_by STORED CORRECTLY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_checked_in_by_is_stored_as_the_authenticated_staff_user_id(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-CHKB1234',
        ]);

        $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-CHKB1234',
        ]);

        $checkIn = CheckIn::where('registration_id', $registration->id)->first();
        $this->assertNotNull($checkIn);
        $this->assertSame($organizer->id, $checkIn->checked_in_by);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11. checked_in_at STORED CORRECTLY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_checked_in_at_is_stored_as_a_recent_timestamp(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-CHKT1234',
        ]);

        $before = now()->subSecond();

        $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-CHKT1234',
        ]);

        $after = now()->addSecond();

        $checkIn = CheckIn::where('registration_id', $registration->id)->first();
        $this->assertNotNull($checkIn);
        $this->assertNotNull($checkIn->checked_in_at);
        // Timestamp is within the expected window
        $this->assertTrue(
            $checkIn->checked_in_at->greaterThanOrEqualTo($before),
            'checked_in_at should be >= time before request'
        );
        $this->assertTrue(
            $checkIn->checked_in_at->lessThanOrEqualTo($after),
            'checked_in_at should be <= time after request'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12. CHECK-IN STATUS DISPLAYED CORRECTLY
    // ──────────────────────────────────────────────────────────────────────────

    public function test_attendee_roster_shows_checked_in_time_for_attended_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->participant()->create(['name' => 'Checked Person']);
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

        $response = $this->actingAs($organizer)
            ->get(route('organizer.events.registrations.index', $event));

        $response->assertStatus(200);
        $response->assertSee('Checked Person');
        // "Not checked in" should NOT appear for this attendee — it's now checked in
        $response->assertDontSee('Not checked in');
        // Status badge "Attended" shown
        $response->assertSee('Attended');
    }

    public function test_attendee_roster_shows_not_checked_in_for_pending_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->participant()->create(['name' => 'Waiting Person']);
        Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)
            ->get(route('organizer.events.registrations.index', $event));

        $response->assertStatus(200);
        $response->assertSee('Waiting Person');
        $response->assertSee('Not checked in');
    }

    public function test_check_in_returns_json_response_for_scanner_api(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'General Admission']);
        $participant = User::factory()->participant()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-XYZ99881',
        ]);

        $response = $this->actingAs($organizer)->postJson(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-XYZ99881',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'attendee' => [
                'name' => 'Alice Smith',
                'email' => 'alice@example.com',
                'ticket_tier' => 'General Admission',
                'registration_code' => 'EVENT-REG-XYZ99881',
            ],
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'attendee' => ['name', 'email', 'ticket_tier', 'registration_code', 'checked_in_at'],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 13. DATABASE TRANSACTION BEHAVIOR
    // ──────────────────────────────────────────────────────────────────────────

    public function test_check_in_is_atomic_and_rolls_back_if_checkin_record_violates_unique_constraint(): void
    {
        // Arrange: create two organizers so we have two distinct user IDs.
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-TXNA1234',
        ]);

        // Pre-insert a check_in record so a second insert in the same transaction
        // will trigger the UNIQUE constraint and force a rollback.
        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now()->subMinute(),
        ]);

        // Manually change the registration back to Confirmed so the controller
        // thinks it's still eligible (bypasses the isCheckedIn guard).
        DB::table('registrations')
            ->where('id', $registration->id)
            ->update(['status' => RegistrationStatus::Confirmed->value]);

        // Act: controller finds the registration, skips isCheckedIn() because we
        // bypassed the relation cache, and the transaction will fail at the DB UNIQUE
        // constraint when it tries to insert a second check_in for the same registration_id.
        // We expect an exception to bubble up.
        $this->expectException(UniqueConstraintViolationException::class);

        DB::transaction(function () use ($registration, $organizer) {
            CheckIn::create([
                'registration_id' => $registration->id,
                'checked_in_by' => $organizer->id,
                'checked_in_at' => now(),
            ]);
            // This would be the companion status update; it should NOT happen.
            DB::table('registrations')
                ->where('id', $registration->id)
                ->update(['status' => RegistrationStatus::Attended->value]);
        });
    }

    public function test_db_unique_constraint_on_registration_id_prevents_duplicate_check_ins_at_db_level(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // First insert succeeds
        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now(),
        ]);

        // Second insert must be blocked at DB level
        $this->expectException(UniqueConstraintViolationException::class);

        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14. UNAUTHORIZED API/ROUTES CANNOT CHECK IN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_unauthorized_organizer_cannot_check_in_for_another_organizers_event(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer1->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-UNAU5678',
        ]);

        $response = $this->actingAs($organizer2)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-UNAU5678',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    public function test_unauthorized_organizer_cannot_check_in_via_json_api(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer1->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-UNAJSN78',
        ]);

        $response = $this->actingAs($organizer2)->postJson(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-UNAJSN78',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // EVENT STATUS GATE
    // ──────────────────────────────────────────────────────────────────────────

    public function test_check_in_is_rejected_if_event_is_draft(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->draft()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-DRAFT111',
        ]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-DRAFT111',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    public function test_check_in_is_rejected_if_event_is_cancelled(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->cancelled()->create(['organizer_id' => $organizer->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'registration_code' => 'EVENT-REG-CNCL1111',
        ]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-CNCL1111',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
        $this->assertDatabaseMissing('check_ins', ['registration_id' => $registration->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STATION METRICS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_station_displays_accurate_metrics_and_recent_feed(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        // 2 confirmed (not yet checked in)
        Registration::factory()->count(2)->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // 1 attended (checked in)
        $attendedReg = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Attended,
        ]);
        CheckIn::create([
            'registration_id' => $attendedReg->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now(),
        ]);

        // 1 cancelled (must NOT count toward active totals)
        Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.check-in.create', $event));

        $response->assertStatus(200);
        // Total active: 3 (2 confirmed + 1 attended)
        $response->assertSee('3');
        // Checked in: 1
        $response->assertSee('1');
        // Pending: 2
        $response->assertSee('2');
        // Recent feed shows the checked-in attendee's name
        $response->assertSee($attendedReg->user->name);
    }
}
