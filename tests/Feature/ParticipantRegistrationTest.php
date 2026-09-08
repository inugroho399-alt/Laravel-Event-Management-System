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

    public function test_registration_stores_correct_event_participant_ticket_code_and_timestamp(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Gold Pass',
            'price' => 75.00,
            'quota' => 25,
        ]);

        $beforeTime = now()->subSecond();

        $response = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);

        $afterTime = now()->addSecond();

        $response->assertRedirect(route('registrations.index'));

        $registration = Registration::where('user_id', $participant->id)->first();
        $this->assertNotNull($registration);

        // 2. Correct event is stored
        $this->assertEquals($event->id, $registration->event_id);
        $this->assertTrue($registration->event->is($event));

        // 3. Correct participant is stored
        $this->assertEquals($participant->id, $registration->user_id);
        $this->assertTrue($registration->user->is($participant));

        // 4. Correct ticket type is stored
        $this->assertEquals($ticket->id, $registration->ticket_type_id);
        $this->assertTrue($registration->ticketType->is($ticket));

        // 5. Registration code is generated
        $this->assertMatchesRegularExpression('/^EVENT-REG-[A-Z0-9]{8}$/', $registration->registration_code);

        // 6. Registration timestamp is stored
        $this->assertNotNull($registration->created_at);
        $this->assertTrue($registration->created_at->between($beforeTime, $afterTime));
    }

    public function test_registration_cannot_be_created_for_another_user_via_payload_tampering(): void
    {
        $attacker = User::factory()->participant()->create();
        $victim = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($attacker)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
            'user_id' => $victim->id, // Attempt to forge registration for victim
        ]);

        $response->assertRedirect(route('registrations.index'));

        // Assert registration was created for the authenticated attacker, NOT the victim
        $this->assertDatabaseHas('registrations', [
            'user_id' => $attacker->id,
            'event_id' => $event->id,
        ]);
        $this->assertDatabaseMissing('registrations', [
            'user_id' => $victim->id,
            'event_id' => $event->id,
        ]);
    }

    public function test_invalid_event_registration_is_rejected(): void
    {
        $participant = User::factory()->participant()->create();
        $ticket = TicketType::factory()->create();

        // Non-existent event returns 404
        $responseNonExistentEvent = $this->actingAs($participant)->post('/events/999999/register', [
            'ticket_type_id' => $ticket->id,
        ]);
        $responseNonExistentEvent->assertStatus(404);

        $event = Event::factory()->published()->create();

        // Non-existent ticket_type_id fails validation
        $responseInvalidTicket = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => 999999,
        ]);
        $responseInvalidTicket->assertSessionHasErrors('ticket_type_id');

        // Missing ticket_type_id fails validation
        $responseMissingTicket = $this->actingAs($participant)->post(route('events.register', $event), []);
        $responseMissingTicket->assertSessionHasErrors('ticket_type_id');
    }

    public function test_cancelled_and_completed_event_registrations_are_rejected(): void
    {
        $participant = User::factory()->participant()->create();

        // Cancelled event
        $cancelledEvent = Event::factory()->cancelled()->create();
        $ticket1 = TicketType::factory()->create(['event_id' => $cancelledEvent->id]);

        $responseCancelled = $this->actingAs($participant)->post(route('events.register', $cancelledEvent), [
            'ticket_type_id' => $ticket1->id,
        ]);
        $responseCancelled->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('registrations', ['event_id' => $cancelledEvent->id]);

        // Completed event
        $completedEvent = Event::factory()->completed()->create();
        $ticket2 = TicketType::factory()->create(['event_id' => $completedEvent->id]);

        $responseCompleted = $this->actingAs($participant)->post(route('events.register', $completedEvent), [
            'ticket_type_id' => $ticket2->id,
        ]);
        $responseCompleted->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('registrations', ['event_id' => $completedEvent->id]);
    }

    public function test_registration_database_relationships_are_fully_functional(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // BelongsTo relationships
        $this->assertInstanceOf(User::class, $registration->user);
        $this->assertTrue($registration->user->is($participant));

        $this->assertInstanceOf(Event::class, $registration->event);
        $this->assertTrue($registration->event->is($event));

        $this->assertInstanceOf(TicketType::class, $registration->ticketType);
        $this->assertTrue($registration->ticketType->is($ticket));

        // Inverse HasMany relationships
        $this->assertTrue($participant->registrations->contains($registration));
        $this->assertTrue($event->registrations->contains($registration));
        $this->assertTrue($ticket->registrations->contains($registration));
    }

    public function test_participant_can_re_register_after_cancelling_prior_registration(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'quota' => 5,
        ]);

        // Register first time
        $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ])->assertRedirect(route('registrations.index'));

        $firstReg = Registration::where('user_id', $participant->id)->first();
        $this->assertEquals(RegistrationStatus::Confirmed, $firstReg->status);

        // Cancel first registration
        $this->actingAs($participant)->post(route('registrations.cancel', $firstReg));
        $this->assertEquals(RegistrationStatus::Cancelled, $firstReg->fresh()->status);
        $this->assertEquals(5, $ticket->fresh()->remainingQuota());

        // Re-register for the same event
        $secondResponse = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $secondResponse->assertRedirect(route('registrations.index'));

        // Should now have 2 registration records: 1 cancelled and 1 confirmed
        $this->assertCount(2, Registration::where('user_id', $participant->id)->get());
        $activeReg = Registration::where('user_id', $participant->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->first();
        $this->assertNotNull($activeReg);
        $this->assertNotEquals($firstReg->registration_code, $activeReg->registration_code);
        $this->assertEquals(4, $ticket->fresh()->remainingQuota());
    }

    public function test_registration_is_transactional_and_rolls_back_on_failure(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'quota' => 10,
        ]);

        // Attach a failing model event listener to simulate unexpected crash inside DB::transaction
        Registration::saving(function () {
            throw new \RuntimeException('Simulated unexpected failure during registration transaction.');
        });

        try {
            $this->actingAs($participant)->post(route('events.register', $event), [
                'ticket_type_id' => $ticket->id,
            ]);
        } catch (\RuntimeException $e) {
            // Expected exception thrown
        }

        // Verify that no registration was persisted and quota remains unaffected
        $this->assertDatabaseEmpty('registrations');
        $this->assertEquals(10, $ticket->fresh()->remainingQuota());

        // Clear listeners for subsequent tests
        Registration::flushEventListeners();
    }

    public function test_complete_participant_registration_flow_from_event_detail_to_my_registrations(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create([
            'title' => 'DevFest Global 2026',
            'slug' => 'devfest-global-2026',
        ]);
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Full Conference Pass',
            'price' => 120.00,
            'quota' => 50,
        ]);

        // Step 1: Participant views event detail
        $detailResponse = $this->actingAs($participant)->get(route('events.show', $event->slug));
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('DevFest Global 2026');
        $detailResponse->assertSee('Full Conference Pass');
        $detailResponse->assertSee('$120.00');
        $detailResponse->assertSee('Register Now');

        // Step 2: Participant selects ticket and registers
        $registerResponse = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);
        $registerResponse->assertRedirect(route('registrations.index'));
        $registerResponse->assertSessionHas('status', 'Registration successful! You have secured your ticket.');

        // Step 3: Registration created and visible in My Registrations
        $registration = Registration::where('user_id', $participant->id)->first();
        $this->assertNotNull($registration);

        $myRegistrationsResponse = $this->actingAs($participant)->get(route('registrations.index'));
        $myRegistrationsResponse->assertStatus(200);
        $myRegistrationsResponse->assertSee('DevFest Global 2026');
        $myRegistrationsResponse->assertSee('Full Conference Pass');
        $myRegistrationsResponse->assertSee($registration->registration_code);
        $myRegistrationsResponse->assertSee('Confirmed');
    }
}
