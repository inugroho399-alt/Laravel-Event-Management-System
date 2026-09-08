<?php

namespace Tests\Unit;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class DatabaseTransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private User $participant;

    private Event $event;

    private TicketType $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $this->participant = User::factory()->create(['role' => UserRole::Participant]);

        $this->event = Event::factory()->create([
            'organizer_id' => $this->organizer->id,
            'status' => EventStatus::Published,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(3),
        ]);

        $this->ticket = TicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Standard Access',
            'price' => 25.00,
            'quota' => 10,
        ]);
    }

    /**
     * Test that if an exception occurs during the registration flow, the entire transaction rolls back cleanly.
     */
    public function test_registration_rolls_back_completely_on_downstream_failure(): void
    {
        $mockQrService = Mockery::mock(QrCodeService::class);
        $mockQrService->shouldReceive('generateAndStore')
            ->once()
            ->andThrow(new \RuntimeException('Storage disk disconnected mid-transaction'));

        $this->app->instance(QrCodeService::class, $mockQrService);

        $initialRegistrationsCount = Registration::count();
        $initialQuota = $this->ticket->remainingQuota();

        try {
            DB::transaction(function () use ($mockQrService) {
                $ticket = TicketType::where('id', $this->ticket->id)->lockForUpdate()->first();

                $registration = Registration::create([
                    'registration_code' => Registration::generateUniqueCode(),
                    'user_id' => $this->participant->id,
                    'event_id' => $this->event->id,
                    'ticket_type_id' => $ticket->id,
                    'status' => RegistrationStatus::Confirmed,
                ]);

                // Simulate downstream failure
                $mockQrService->generateAndStore($registration);
            });

            $this->fail('Transaction should have failed with RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('Storage disk disconnected mid-transaction', $e->getMessage());
        }

        // Verify clean rollback in database
        $this->assertSame($initialRegistrationsCount, Registration::count());
        $this->assertSame($initialQuota, $this->ticket->fresh()->remainingQuota());
        $this->assertDatabaseMissing('registrations', [
            'user_id' => $this->participant->id,
            'event_id' => $this->event->id,
        ]);
    }

    /**
     * Test check-in transactional rollback when an exception occurs before commit.
     */
    public function test_checkin_rolls_back_if_exception_occurs_before_transaction_commit(): void
    {
        $registration = Registration::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'ticket_type_id' => $this->ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        try {
            DB::transaction(function () use ($registration) {
                CheckIn::create([
                    'registration_id' => $registration->id,
                    'checked_in_by' => $this->organizer->id,
                    'checked_in_at' => now(),
                ]);

                $registration->update(['status' => RegistrationStatus::Attended]);

                // Simulate system crash or secondary webhook failure
                throw new \RuntimeException('Scanner device disconnected before handshake completion');
            });

            $this->fail('Transaction should have thrown RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('Scanner device disconnected before handshake completion', $e->getMessage());
        }

        // Database state must be unmutated
        $this->assertSame(0, CheckIn::count());
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertFalse($registration->fresh()->isCheckedIn());
    }

    /**
     * Test cascading deletion when an Event is deleted.
     */
    public function test_cascading_deletion_removes_tickets_registrations_and_checkins_when_event_is_deleted(): void
    {
        $registration = Registration::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'ticket_type_id' => $this->ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $checkIn = CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $this->organizer->id,
            'checked_in_at' => now(),
        ]);

        $this->assertDatabaseHas('events', ['id' => $this->event->id]);
        $this->assertDatabaseHas('ticket_types', ['id' => $this->ticket->id]);
        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('check_ins', ['id' => $checkIn->id]);

        // Delete Event
        $this->event->delete();

        // Check foreign key cascades
        $this->assertDatabaseMissing('events', ['id' => $this->event->id]);
        $this->assertDatabaseMissing('ticket_types', ['id' => $this->ticket->id]);
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('check_ins', ['id' => $checkIn->id]);
    }

    /**
     * Test cascading deletion when a User (Participant) is deleted.
     */
    public function test_cascading_deletion_removes_registrations_and_checkins_when_participant_is_deleted(): void
    {
        $registration = Registration::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'ticket_type_id' => $this->ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $checkIn = CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $this->organizer->id,
            'checked_in_at' => now(),
        ]);

        // Delete Participant
        $this->participant->delete();

        $this->assertDatabaseMissing('users', ['id' => $this->participant->id]);
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('check_ins', ['id' => $checkIn->id]);

        // Event and ticket type must still exist
        $this->assertDatabaseHas('events', ['id' => $this->event->id]);
        $this->assertDatabaseHas('ticket_types', ['id' => $this->ticket->id]);
    }

    /**
     * Test cascading deletion when a TicketType is deleted.
     */
    public function test_cascading_deletion_removes_registrations_when_ticket_type_is_deleted(): void
    {
        $registration = Registration::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'ticket_type_id' => $this->ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->ticket->delete();

        $this->assertDatabaseMissing('ticket_types', ['id' => $this->ticket->id]);
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
    }

    /**
     * Test registration code uniqueness is enforced strictly at the database schema level.
     */
    public function test_registration_code_uniqueness_is_enforced_by_database_constraint(): void
    {
        $code = 'EVT-TEST-UNIQUE-CODE';

        Registration::factory()->create([
            'registration_code' => $code,
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'ticket_type_id' => $this->ticket->id,
        ]);

        $anotherParticipant = User::factory()->create(['role' => UserRole::Participant]);

        $this->expectException(QueryException::class);

        Registration::create([
            'registration_code' => $code, // duplicate code violates unique constraint
            'event_id' => $this->event->id,
            'user_id' => $anotherParticipant->id,
            'ticket_type_id' => $this->ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
    }

    /**
     * Test check-in registration_id uniqueness is strictly enforced at the database schema level.
     */
    public function test_checkin_registration_id_uniqueness_is_enforced_by_database_constraint(): void
    {
        $registration = Registration::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'ticket_type_id' => $this->ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $this->organizer->id,
            'checked_in_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        CheckIn::create([
            'registration_id' => $registration->id, // duplicate registration_id violates unique constraint
            'checked_in_by' => $this->organizer->id,
            'checked_in_at' => now(),
        ]);
    }
}
