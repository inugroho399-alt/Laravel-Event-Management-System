<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_register_and_is_redirected_to_login(): void
    {
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $response = $this->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseEmpty('registrations');
    }

    public function test_participant_can_register_for_an_open_published_event(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 25.00,
            'quota' => 50,
        ]);

        $response = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);

        $response->assertRedirect(route('registrations.index'));
        $this->assertDatabaseHas('registrations', [
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        $registration = Registration::where('user_id', $participant->id)->first();
        $this->assertNotNull($registration);
        $this->assertStringStartsWith('EVENT-REG-', $registration->registration_code);
    }

    public function test_registration_decrements_available_ticket_quota(): void
    {
        $user1 = User::factory()->participant()->create();
        $user2 = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'quota' => 10,
        ]);

        $this->assertEquals(10, $ticket->remainingQuota());

        $this->actingAs($user1)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $this->assertEquals(9, $ticket->fresh()->remainingQuota());

        $this->actingAs($user2)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $this->assertEquals(8, $ticket->fresh()->remainingQuota());
    }

    public function test_system_prevents_duplicate_registration_for_the_same_event(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'quota' => 10,
        ]);

        // First registration succeeds
        $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ])->assertRedirect(route('registrations.index'));

        // Second registration attempt fails
        $secondAttempt = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);

        $secondAttempt->assertSessionHasErrors('error');
        $this->assertCount(1, Registration::where('user_id', $participant->id)->get());
    }

    public function test_system_prevents_registration_when_ticket_quota_is_exhausted(): void
    {
        $user1 = User::factory()->participant()->create();
        $user2 = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'quota' => 1,
        ]);

        // User 1 gets the only ticket
        $this->actingAs($user1)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $this->assertTrue($ticket->fresh()->isSoldOut());

        // User 2 attempts to register for sold-out ticket
        $response = $this->actingAs($user2)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('registrations', ['user_id' => $user2->id]);
    }

    public function test_system_prevents_registration_when_event_is_not_open(): void
    {
        $participant = User::factory()->participant()->create();
        $draftEvent = Event::factory()->draft()->create();
        $ticket = TicketType::factory()->create(['event_id' => $draftEvent->id]);

        $response = $this->actingAs($participant)->post(route('events.register', $draftEvent), [
            'ticket_type_id' => $ticket->id,
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseEmpty('registrations');

        $cancelledEvent = Event::factory()->cancelled()->create();
        $ticket2 = TicketType::factory()->create(['event_id' => $cancelledEvent->id]);

        $responseCancelled = $this->actingAs($participant)->post(route('events.register', $cancelledEvent), [
            'ticket_type_id' => $ticket2->id,
        ]);

        $responseCancelled->assertSessionHasErrors('error');
    }

    public function test_registration_requires_valid_ticket_belonging_to_the_event(): void
    {
        $participant = User::factory()->participant()->create();
        $eventA = Event::factory()->published()->create();
        $eventB = Event::factory()->published()->create();
        $ticketOfEventB = TicketType::factory()->create(['event_id' => $eventB->id]);

        $response = $this->actingAs($participant)->post(route('events.register', $eventA), [
            'ticket_type_id' => $ticketOfEventB->id,
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseEmpty('registrations');
    }

    public function test_participant_can_view_their_registered_events(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create(['title' => 'Global AI Summit']);
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'All Access Pass',
            'price' => 199.00,
        ]);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.index'));

        $response->assertStatus(200);
        $response->assertSee('Global AI Summit');
        $response->assertSee('All Access Pass');
        $response->assertSee('$199.00');
        $response->assertSee($registration->registration_code);
        $response->assertSee('Confirmed');
    }

    public function test_participant_can_cancel_their_registration(): void
    {
        $participant = User::factory()->participant()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($participant)->post(route('registrations.cancel', $registration));

        $response->assertRedirect();
        $this->assertEquals(RegistrationStatus::Cancelled, $registration->fresh()->status);
    }

    public function test_cancelling_registration_restores_ticket_quota(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'quota' => 5,
        ]);

        $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $this->assertEquals(4, $ticket->fresh()->remainingQuota());

        $registration = Registration::where('user_id', $participant->id)->first();
        $this->actingAs($participant)->post(route('registrations.cancel', $registration));

        // Quota is back to 5
        $this->assertEquals(5, $ticket->fresh()->remainingQuota());
    }

    public function test_participant_cannot_cancel_another_participants_registration(): void
    {
        $participant1 = User::factory()->participant()->create();
        $participant2 = User::factory()->participant()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant1->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($participant2)->post(route('registrations.cancel', $registration));

        $response->assertStatus(403);
        $this->assertEquals(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }

    public function test_organizer_can_view_attendees_for_their_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'VIP Tier']);
        $attendee = User::factory()->participant()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $registration = Registration::factory()->create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.registrations.index', $event));

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('john@example.com');
        $response->assertSee('VIP Tier');
        $response->assertSee($registration->registration_code);
    }

    public function test_organizer_cannot_view_attendees_for_another_organizers_event(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer1->id]);

        $response = $this->actingAs($organizer2)->get(route('organizer.events.registrations.index', $event));

        $response->assertStatus(403);
    }

    public function test_admin_can_view_attendees_for_any_event(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($admin)->get(route('organizer.events.registrations.index', $event));

        $response->assertStatus(200);
    }
}
