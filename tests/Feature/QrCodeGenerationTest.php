<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_generates_qr_code_file_in_storage(): void
    {
        Storage::fake('public');

        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);

        $response->assertRedirect(route('registrations.index'));

        $registration = Registration::where('user_id', $participant->id)->first();
        $this->assertNotNull($registration);
        $this->assertNotNull($registration->qr_code_path);
        $this->assertEquals('qrcodes/'.$registration->registration_code.'.svg', $registration->qr_code_path);

        Storage::disk('public')->assertExists($registration->qr_code_path);
    }

    public function test_qr_code_encodes_strictly_registration_code_without_sensitive_data(): void
    {
        Storage::fake('public');

        $participant = User::factory()->participant()->create([
            'name' => 'John Sensitive Doe',
            'email' => 'john.sensitive@example.com',
        ]);
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        $this->actingAs($participant)->post(route('events.register', $event), [
            'ticket_type_id' => $ticket->id,
        ]);

        $registration = Registration::where('user_id', $participant->id)->first();
        $qrCodeSvg = Storage::disk('public')->get($registration->qr_code_path);

        // SVG should exist and not leak personal attendee information
        $this->assertNotEmpty($qrCodeSvg);
        $this->assertStringNotContainsString('john.sensitive@example.com', $qrCodeSvg);
        $this->assertStringNotContainsString('John Sensitive Doe', $qrCodeSvg);
    }

    public function test_qr_code_is_pure_vector_svg_format(): void
    {
        $qrService = app(QrCodeService::class);
        $svg = $qrService->generateSvgString('EVENT-REG-TEST1234');

        $this->assertStringStartsWith('<?xml', $svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_digital_ticket_renders_qr_code_svg(): void
    {
        Storage::fake('public');

        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('<svg', false);
        $response->assertSee($registration->registration_code);
        $response->assertSee('Scan QR code at venue entrance for instant check-in.');
    }

    public function test_qr_code_is_generated_on_demand_if_initially_missing(): void
    {
        Storage::fake('public');

        $participant = User::factory()->participant()->create();
        $event = Event::factory()->published()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);

        // Registration created with null qr_code_path (e.g. legacy record)
        $registration = Registration::factory()->create([
            'user_id' => $participant->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'qr_code_path' => null,
        ]);

        $response = $this->actingAs($participant)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $this->assertNotNull($registration->fresh()->qr_code_path);
        Storage::disk('public')->assertExists($registration->fresh()->qr_code_path);
    }

    public function test_organizer_can_view_attendee_qr_code_on_digital_ticket(): void
    {
        Storage::fake('public');

        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $attendee = User::factory()->participant()->create();
        $ticket = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
        ]);

        $response = $this->actingAs($organizer)->get(route('registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('<svg', false);
        $response->assertSee($registration->registration_code);
    }

    public function test_unauthorized_participant_cannot_view_another_participants_qr_code(): void
    {
        $participantA = User::factory()->participant()->create();
        $participantB = User::factory()->participant()->create();
        $registrationB = Registration::factory()->create([
            'user_id' => $participantB->id,
        ]);

        $response = $this->actingAs($participantA)->get(route('registrations.show', $registrationB));

        $response->assertStatus(403);
    }
}
