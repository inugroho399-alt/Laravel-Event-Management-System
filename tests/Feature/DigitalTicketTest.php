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

class DigitalTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_digital_ticket_and_is_redirected_to_login(): void
    {
        $registration = Registration::factory()->create();

        $response = $this->get(route('registrations.show', $registration));

        $response->assertRedirect('/login');
    }

    public function test_participant_can_view_their_own_digital_ticket(): void
    {
        $participant = User::factory()->participant()->create(['name' => 'Sarah Connor', 'email' => 'sarah@example.com']);
        $event = Event::factory()->published()->create([
            'title' => 'Cybersecurity Summit 2026',
            'location' => 'Grand Ballroom, Tech Center',
        ]);
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'All-Access Pass',
            'price' => 150.00,
        ]);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('Cybersecurity Summit 2026');
        $response->assertSee('Sarah Connor');
        $response->assertSee('sarah@example.com');
        $response->assertSee('All-Access Pass');
        $response->assertSee('$150.00');
        $response->assertSee($registration->registration_code);
        $response->assertSee('Confirmed');
        $response->assertSee('Grand Ballroom, Tech Center');
    }

    public function test_digital_ticket_displays_free_ticket_correctly(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $freeTicket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Community Free Entry',
            'price' => 0.00,
        ]);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $freeTicket->id,
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('Community Free Entry');
        $response->assertSee('Free Admission');
    }

    public function test_digital_ticket_displays_event_organizer_and_date(): void
    {
        $organizer = User::factory()->organizer()->create(['name' => 'Apex Event Planners']);
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Innovate Expo 2026',
        ]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->participant()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('Organized by Apex Event Planners');
        $response->assertSee($event->start_date->format('l, F d, Y'));
        $response->assertSee($event->location);
    }

    public function test_digital_ticket_displays_awaiting_check_in_when_not_checked_in(): void
    {
        $participant = User::factory()->participant()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('Awaiting Check-in');
        $response->assertSee('Scan at entrance');
    }

    public function test_digital_ticket_displays_checked_in_status_when_check_in_exists(): void
    {
        $participant = User::factory()->participant()->create();
        $organizer = User::factory()->organizer()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        CheckIn::factory()->create([
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
            'checked_in_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('Checked In');
        $response->assertDontSee('Awaiting Check-in');
    }

    public function test_digital_ticket_displays_cancelled_status(): void
    {
        $participant = User::factory()->participant()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Cancelled,
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('Cancelled');
        $response->assertSee('Ticket released');
    }

    public function test_organizer_can_view_digital_ticket_for_their_event_attendee(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $attendee = User::factory()->participant()->create(['name' => 'Alice Smith']);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
        ]);

        $response = $this->actingAs($organizer)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('Alice Smith');
        $response->assertSee($registration->registration_code);
    }

    public function test_organizer_cannot_view_digital_ticket_for_another_organizers_event(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer1->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
        ]);

        $response = $this->actingAs($organizer2)->get(route('registrations.show', $registration));

        $response->assertStatus(403);
    }

    public function test_participant_cannot_view_another_participants_digital_ticket(): void
    {
        $participant1 = User::factory()->participant()->create();
        $participant2 = User::factory()->participant()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant1->id,
        ]);

        $response = $this->actingAs($participant2)->get(route('registrations.show', $registration));

        $response->assertStatus(403);
    }

    public function test_admin_can_view_digital_ticket_for_any_registration(): void
    {
        $admin = User::factory()->admin()->create();
        $participant = User::factory()->participant()->create(['name' => 'Bob Marley']);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
        ]);

        $response = $this->actingAs($admin)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('Bob Marley');
        $response->assertSee($registration->registration_code);
    }

    public function test_digital_ticket_returns_404_for_non_existent_registration(): void
    {
        $user = User::factory()->participant()->create();

        $response = $this->actingAs($user)->get('/my-registrations/999999');

        $response->assertStatus(404);
    }

    public function test_participant_can_cancel_registration_from_digital_ticket_page(): void
    {
        $participant = User::factory()->participant()->create();
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($participant)->from(route('registrations.show', $registration))
            ->post(route('registrations.cancel', $registration));

        $response->assertRedirect(route('registrations.show', $registration));
        $this->assertEquals(RegistrationStatus::Cancelled, $registration->fresh()->status);
    }
}
