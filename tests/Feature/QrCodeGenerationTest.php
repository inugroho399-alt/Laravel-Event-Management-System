<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Services\QrCodeService;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\PlainTextRenderer;
use Illuminate\Database\QueryException;
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

    public function test_every_registration_has_an_appropriate_unique_identifier(): void
    {
        $registrations = Registration::factory()->count(10)->create();
        $codes = $registrations->pluck('registration_code');

        // All codes are unique
        $this->assertCount(10, $codes->unique());

        // All codes match expected format: EVENT-REG-XXXXXXXX
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^EVENT-REG-[A-Z0-9]{8}$/', $code);
        }

        // Database unique constraint is enforced
        $this->expectException(QueryException::class);
        Registration::factory()->create(['registration_code' => $codes->first()]);
    }

    public function test_qr_code_encodes_and_produces_standard_scannable_matrix(): void
    {
        $code = 'EVENT-REG-SCANTEST';
        $service = app(QrCodeService::class);
        $svg = $service->generateSvgString($code);

        // Verify SVG has standard XML, dimensions, and path data
        $this->assertStringContainsString('viewBox="0 0 200 200"', $svg);
        $this->assertStringContainsString('fill="#000000"', $svg);
        $this->assertStringContainsString('fill="#ffffff"', $svg);

        // Verify underlying encoder matrix
        $qr = Encoder::encode($code, ErrorCorrectionLevel::M());
        $this->assertNotNull($qr->getMatrix());
        $this->assertEquals(21, $qr->getMatrix()->getWidth());
        $this->assertEquals(21, $qr->getMatrix()->getHeight());

        // Verify text renderer can output terminal-scannable UTF-8 blocks
        $textRenderer = new PlainTextRenderer;
        $textQr = $textRenderer->render($qr);
        $this->assertNotEmpty($textQr);
    }

    public function test_invalid_registration_identifiers_are_safely_rejected(): void
    {
        // Querying non-existent registration code returns null
        $this->assertNull(Registration::where('registration_code', 'EVENT-REG-NONEXIST')->first());
        $this->assertNull(Registration::where('registration_code', '')->first());
        $this->assertNull(Registration::where('registration_code', 'MALICIOUS"OR 1=1--')->first());

        // Requesting non-existent ID via web route returns 404
        $user = User::factory()->participant()->create();
        $this->actingAs($user)->get('/my-registrations/999999')->assertStatus(404);
        $this->actingAs($user)->get('/my-registrations/invalid-format')->assertStatus(404);
    }

    public function test_qr_code_cannot_be_used_to_access_another_participants_private_data(): void
    {
        $attacker = User::factory()->participant()->create(['name' => 'Attacker User', 'email' => 'attacker@test.com']);
        $victim = User::factory()->participant()->create(['name' => 'Victim User', 'email' => 'victim@secret.com']);

        $registrationVictim = Registration::factory()->create([
            'user_id' => $victim->id,
            'registration_code' => 'EVENT-REG-VICTIM01',
        ]);

        // Attacker attempts to open victim's ticket using victim's registration record
        $response = $this->actingAs($attacker)->get(route('registrations.show', $registrationVictim));

        // Denied with 403 Forbidden
        $response->assertStatus(403);
        $response->assertDontSee('Victim User');
        $response->assertDontSee('victim@secret.com');
        $response->assertDontSee('EVENT-REG-VICTIM01');
    }
}
