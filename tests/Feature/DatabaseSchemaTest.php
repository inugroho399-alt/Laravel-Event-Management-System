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
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_roles_and_helpers(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        $this->assertEquals(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isOrganizer());

        $this->assertEquals(UserRole::Organizer, $organizer->role);
        $this->assertTrue($organizer->isOrganizer());

        $this->assertEquals(UserRole::Participant, $participant->role);
        $this->assertTrue($participant->isParticipant());
    }

    public function test_user_event_and_registration_relationships(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $participant = User::factory()->participant()->create();
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $this->assertTrue($organizer->events->contains($event));
        $this->assertEquals($organizer->id, $event->organizer->id);
        $this->assertTrue($participant->registrations->contains($registration));
    }

    public function test_event_ticket_types_and_status_casts(): void
    {
        $event = Event::factory()->published()->create();
        $ticket1 = TicketType::factory()->create(['event_id' => $event->id, 'quota' => 100]);
        $ticket2 = TicketType::factory()->create(['event_id' => $event->id, 'quota' => 50]);

        $this->assertEquals(EventStatus::Published, $event->status);
        $this->assertTrue($event->isPublished());
        $this->assertTrue($event->isRegistrationOpen());
        $this->assertCount(2, $event->ticketTypes);
        $this->assertEquals($event->id, $ticket1->event->id);
    }

    public function test_ticket_type_quota_calculations(): void
    {
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'quota' => 2]);

        $this->assertEquals(2, $ticket->remainingQuota());
        $this->assertFalse($ticket->isSoldOut());

        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->assertEquals(1, $ticket->fresh()->remainingQuota());
        $this->assertFalse($ticket->fresh()->isSoldOut());

        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->assertEquals(0, $ticket->fresh()->remainingQuota());
        $this->assertTrue($ticket->fresh()->isSoldOut());
    }

    public function test_registration_code_generation_and_check_in_relationship(): void
    {
        $registration = Registration::factory()->create();

        $this->assertStringStartsWith('EVENT-REG-', $registration->registration_code);
        $this->assertEquals(RegistrationStatus::Confirmed, $registration->status);
        $this->assertFalse($registration->isCheckedIn());

        $checker = User::factory()->organizer()->create();
        $checkIn = CheckIn::factory()->create([
            'registration_id' => $registration->id,
            'checked_in_by' => $checker->id,
        ]);

        $this->assertTrue($registration->fresh()->isCheckedIn());
        $this->assertEquals($checkIn->id, $registration->fresh()->checkIn->id);
        $this->assertEquals($checker->id, $checkIn->checker->id);
    }

    public function test_duplicate_check_in_is_prevented_by_database_constraint(): void
    {
        $registration = Registration::factory()->create();
        $checker = User::factory()->organizer()->create();

        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $checker->id,
            'checked_in_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        CheckIn::create([
            'registration_id' => $registration->id,
            'checked_in_by' => $checker->id,
            'checked_in_at' => now(),
        ]);
    }

    public function test_cascading_deletes_clean_up_child_records(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
        ]);
        $checkIn = CheckIn::factory()->create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
        ]);

        $event->delete();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('ticket_types', ['id' => $ticket->id]);
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('check_ins', ['id' => $checkIn->id]);
    }
}
