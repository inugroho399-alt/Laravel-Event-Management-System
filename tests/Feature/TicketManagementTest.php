<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_view_ticket_types_for_their_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'VIP Pass',
            'price' => 150.00,
            'quota' => 50,
        ]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.tickets.index', $event));

        $response->assertStatus(200);
        $response->assertSee('VIP Pass');
        $response->assertSee('$150.00');
        $response->assertSee('50');
    }

    public function test_organizer_can_view_ticket_creation_page(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->get(route('organizer.events.tickets.create', $event));

        $response->assertStatus(200);
        $response->assertSee('Add New Ticket Type');
    }

    public function test_organizer_can_create_ticket_type_with_valid_data(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $payload = [
            'name' => 'Early Bird Access',
            'description' => 'Early entry with exclusive perks.',
            'price' => 45.00,
            'quota' => 100,
        ];

        $response = $this->actingAs($organizer)->post(route('organizer.events.tickets.store', $event), $payload);

        $response->assertRedirect(route('organizer.events.tickets.index', $event));
        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $event->id,
            'name' => 'Early Bird Access',
            'price' => 45.00,
            'quota' => 100,
        ]);
    }

    public function test_organizer_can_create_free_ticket_type(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $payload = [
            'name' => 'Free Community Ticket',
            'description' => 'Free general entry.',
            'price' => 0,
            'quota' => 200,
        ];

        $response = $this->actingAs($organizer)->post(route('organizer.events.tickets.store', $event), $payload);

        $response->assertRedirect(route('organizer.events.tickets.index', $event));
        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $event->id,
            'name' => 'Free Community Ticket',
            'price' => 0,
            'quota' => 200,
        ]);
    }

    public function test_ticket_creation_validates_required_fields(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.tickets.store', $event), []);

        $response->assertSessionHasErrors(['name', 'price', 'quota']);
    }

    public function test_ticket_creation_validates_price_and_quota_ranges(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.tickets.store', $event), [
            'name' => 'Faulty Ticket',
            'price' => -10.00, // negative price
            'quota' => 0,      // zero quota
        ]);

        $response->assertSessionHasErrors(['price', 'quota']);
    }

    public function test_organizer_can_update_their_ticket_type(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Standard Ticket',
            'price' => 20.00,
            'quota' => 100,
        ]);

        $response = $this->actingAs($organizer)->put(route('organizer.events.tickets.update', [$event, $ticket]), [
            'name' => 'Standard Ticket (Updated)',
            'description' => 'Includes lunch buffet.',
            'price' => 25.00,
            'quota' => 120,
        ]);

        $response->assertRedirect(route('organizer.events.tickets.index', $event));
        $this->assertDatabaseHas('ticket_types', [
            'id' => $ticket->id,
            'name' => 'Standard Ticket (Updated)',
            'price' => 25.00,
            'quota' => 120,
        ]);
    }

    public function test_organizer_cannot_reduce_quota_below_registered_attendees_count(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'quota' => 10,
        ]);

        // Create 3 active registrations for this ticket
        Registration::factory()->count(3)->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        // Attempting to set quota to 2 should fail
        $response = $this->actingAs($organizer)->put(route('organizer.events.tickets.update', [$event, $ticket]), [
            'name' => $ticket->name,
            'price' => $ticket->price,
            'quota' => 2,
        ]);

        $response->assertSessionHasErrors('quota');
        $this->assertEquals(10, $ticket->fresh()->quota);
    }

    public function test_organizer_can_delete_ticket_type_without_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($organizer)->delete(route('organizer.events.tickets.destroy', [$event, $ticket]));

        $response->assertRedirect(route('organizer.events.tickets.index', $event));
        $this->assertDatabaseMissing('ticket_types', ['id' => $ticket->id]);
    }

    public function test_organizer_cannot_delete_ticket_type_with_active_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->actingAs($organizer)->delete(route('organizer.events.tickets.destroy', [$event, $ticket]));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('ticket_types', ['id' => $ticket->id]);
    }

    public function test_organizer_cannot_manage_tickets_for_another_organizers_event(): void
    {
        $organizer1 = User::factory()->organizer()->create();
        $organizer2 = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer1->id]);
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        // Attempting to view index
        $this->actingAs($organizer2)->get(route('organizer.events.tickets.index', $event))->assertStatus(403);

        // Attempting to view create
        $this->actingAs($organizer2)->get(route('organizer.events.tickets.create', $event))->assertStatus(403);

        // Attempting to store
        $this->actingAs($organizer2)->post(route('organizer.events.tickets.store', $event), [
            'name' => 'Hijacked Ticket',
            'price' => 10,
            'quota' => 10,
        ])->assertStatus(403);

        // Attempting to edit
        $this->actingAs($organizer2)->get(route('organizer.events.tickets.edit', [$event, $ticket]))->assertStatus(403);

        // Attempting to update
        $this->actingAs($organizer2)->put(route('organizer.events.tickets.update', [$event, $ticket]), [
            'name' => 'Hijacked Update',
            'price' => 10,
            'quota' => 10,
        ])->assertStatus(403);

        // Attempting to delete
        $this->actingAs($organizer2)->delete(route('organizer.events.tickets.destroy', [$event, $ticket]))->assertStatus(403);
    }

    public function test_participant_cannot_access_organizer_ticket_management(): void
    {
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->create();

        $this->actingAs($participant)->get(route('organizer.events.tickets.index', $event))->assertStatus(403);
        $this->actingAs($participant)->get(route('organizer.events.tickets.create', $event))->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login_when_accessing_ticket_management(): void
    {
        $event = Event::factory()->create();

        $this->get(route('organizer.events.tickets.index', $event))->assertRedirect('/login');
    }

    public function test_admin_can_manage_tickets_for_any_event(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        // Admin can view
        $this->actingAs($admin)->get(route('organizer.events.tickets.index', $event))->assertStatus(200);

        // Admin can create
        $this->actingAs($admin)->post(route('organizer.events.tickets.store', $event), [
            'name' => 'Admin Special Pass',
            'price' => 99.00,
            'quota' => 50,
        ])->assertRedirect(route('organizer.events.tickets.index', $event));

        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $event->id,
            'name' => 'Admin Special Pass',
        ]);
    }

    public function test_public_event_detail_page_displays_tickets_and_availability(): void
    {
        $event = Event::factory()->published()->create();
        $availableTicket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Standard Entry',
            'price' => 30.00,
            'quota' => 50,
        ]);
        $soldOutTicket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Super Early Bird',
            'price' => 10.00,
            'quota' => 1,
        ]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $soldOutTicket->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertStatus(200);
        $response->assertSee('Standard Entry');
        $response->assertSee('$30.00');
        $response->assertSee('Super Early Bird');
        $response->assertSee('Sold Out');
    }
}
