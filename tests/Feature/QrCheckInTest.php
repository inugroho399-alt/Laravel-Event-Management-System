<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class QrCheckInTest extends TestCase
{
    use RefreshDatabase;

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
                return str_contains($message, $registration->registration_code) &&
                       str_contains($message, (string) $event->id) &&
                       str_contains($message, (string) $organizer->id);
            });

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-ABC12345',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('check_ins', [
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
        ]);

        $registration->refresh();
        $this->assertEquals(RegistrationStatus::Attended, $registration->status);
        $this->assertTrue($registration->isCheckedIn());
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
            'attendee' => [
                'name',
                'email',
                'ticket_tier',
                'registration_code',
                'checked_in_at',
            ],
        ]);
    }

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
        $this->assertEquals(1, CheckIn::where('registration_id', $registration->id)->count());
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
        $this->assertEquals(1, CheckIn::where('registration_id', $registration->id)->count());
    }

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
        $this->assertDatabaseMissing('check_ins', [
            'registration_id' => $registration->id,
        ]);
    }

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

        // Attempting to check in event2's code under event1 station
        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event1), [
            'registration_code' => 'EVENT-REG-DIFF2222',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
        $this->assertDatabaseMissing('check_ins', [
            'registration_id' => $registration2->id,
        ]);
    }

    public function test_non_existent_registration_code_is_rejected(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'EVENT-REG-00000000',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
    }

    public function test_invalid_registration_code_format_fails_validation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $response = $this->actingAs($organizer)->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => 'INVALID-CODE',
        ]);

        $response->assertSessionHasErrors(['registration_code']);
    }

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
        $this->assertDatabaseMissing('check_ins', [
            'registration_id' => $registration->id,
        ]);
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
        $this->assertDatabaseMissing('check_ins', [
            'registration_id' => $registration->id,
        ]);
    }

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

        // 1 cancelled (excluded from active counts)
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
        // Recent check-in feed includes attendee name
        $response->assertSee($attendedReg->user->name);
    }
}
