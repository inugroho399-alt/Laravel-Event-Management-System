<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TEST PHASE 15 — END-TO-END SYSTEM TEST
 *
 * Full lifecycle multi-role integration scenario:
 * 1. Guest registers a new participant account.
 * 2. Organizer logs in, creates a new event, configures multiple ticket tiers (Free + VIP), and publishes it.
 * 3. Participant searches and discovers the event on the public live hub.
 * 4. Participant selects a VIP ticket tier and completes checkout/registration.
 * 5. System atomically decrements ticket quota, generates unique registration code, and generates SVG QR code in storage.
 * 6. Participant navigates to "My Registrations" and views their digital boarding-pass ticket.
 * 7. On event day, Organizer accesses the live QR check-in workstation.
 * 8. Organizer inputs/scans the attendee's registration code: check-in is successfully processed and recorded.
 * 9. Duplicate check-in attempt is immediately rejected with an error.
 * 10. Organizer reviews the event analytics report: total registrations, checked-in count, turnout rate, and revenue are 100% accurate.
 * 11. Admin accesses platform oversight: sees global activity feed, updated user counts, event totals, and revenue metrics.
 */
class EndToEndSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_event_lifecycle_from_guest_registration_to_checkin_and_reporting(): void
    {
        Storage::fake('public');

        // ──────────────────────────────────────────────────────────────────────
        // STEP 1: GUEST REGISTRATION
        // ──────────────────────────────────────────────────────────────────────
        $guestRegisterResponse = $this->post('/register', [
            'name' => 'Maya Lin',
            'email' => 'maya.lin@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $guestRegisterResponse->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $participant = User::where('email', 'maya.lin@example.com')->first();
        $this->assertNotNull($participant);
        $this->assertEquals(UserRole::Participant, $participant->role);

        // Log out participant for now
        $this->post('/logout');
        $this->assertGuest();

        // ──────────────────────────────────────────────────────────────────────
        // STEP 2: ORGANIZER CREATES EVENT AND TICKET TIERS
        // ──────────────────────────────────────────────────────────────────────
        $organizer = User::factory()->create([
            'name' => 'Global Tech Summits Inc.',
            'email' => 'host@globaltechsummits.io',
            'role' => UserRole::Organizer,
        ]);

        // Organizer logs in
        $loginResponse = $this->post('/login', [
            'email' => 'host@globaltechsummits.io',
            'password' => 'password',
        ]);
        $loginResponse->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($organizer);

        // Organizer creates event
        $createEventResponse = $this->post(route('organizer.events.store'), [
            'title' => 'International AI & Robotics Conference 2026',
            'description' => 'A premier world-class gathering of deep learning researchers, roboticists, and engineers.',
            'location' => 'San Francisco Moscone Center, Hall A',
            'start_date' => now()->addDays(14)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(16)->format('Y-m-d\TH:i'),
            'status' => EventStatus::Published->value,
        ]);

        $event = Event::where('title', 'International AI & Robotics Conference 2026')->first();
        $createEventResponse->assertRedirect(route('organizer.events.index'));
        $this->assertEquals(EventStatus::Published, $event->status);

        // Organizer creates Ticket Tier 1: General Admission (Free)
        $freeTicketResponse = $this->post(route('organizer.events.tickets.store', $event), [
            'name' => 'General Admission',
            'price' => 0.00,
            'quota' => 100,
            'description' => 'Full access to main keynotes and exhibition floor.',
        ]);
        $freeTicketResponse->assertRedirect(route('organizer.events.tickets.index', $event));

        // Organizer creates Ticket Tier 2: VIP All-Access ($250.00)
        $vipTicketResponse = $this->post(route('organizer.events.tickets.store', $event), [
            'name' => 'VIP All-Access',
            'price' => 250.00,
            'quota' => 25,
            'description' => 'Keynotes, networking dinner, VIP lounge, and workshop tracks.',
        ]);
        $vipTicketResponse->assertRedirect(route('organizer.events.tickets.index', $event));

        $this->assertEquals(2, $event->ticketTypes()->count());
        $vipTicket = $event->ticketTypes()->where('name', 'VIP All-Access')->first();
        $this->assertEquals(25, $vipTicket->quota);

        // Log out organizer
        $this->post('/logout');

        // ──────────────────────────────────────────────────────────────────────
        // STEP 3: PARTICIPANT DISCOVERS EVENT VIA CATALOG
        // ──────────────────────────────────────────────────────────────────────
        $this->actingAs($participant);

        $catalogResponse = $this->get(route('events.index'));
        $catalogResponse->assertStatus(200);
        $catalogResponse->assertSee('International AI & Robotics Conference 2026');
        $catalogResponse->assertSee('San Francisco Moscone Center, Hall A');

        // Search for the event specifically
        $searchResponse = $this->get(route('events.index', ['search' => 'Robotics']));
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('International AI & Robotics Conference 2026');

        // View event details
        $detailResponse = $this->get(route('events.show', $event));
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('International AI & Robotics Conference 2026');
        $detailResponse->assertSee('VIP All-Access');
        $detailResponse->assertSee('$250.00');

        // ──────────────────────────────────────────────────────────────────────
        // STEP 4 & 5: REGISTRATION, QUOTA DECREMENT, AND PASS GENERATION
        // ──────────────────────────────────────────────────────────────────────
        $registerResponse = $this->post(route('events.register', $event), [
            'ticket_type_id' => $vipTicket->id,
        ]);

        $registration = Registration::where('event_id', $event->id)
            ->where('user_id', $participant->id)
            ->first();

        $registerResponse->assertRedirect(route('registrations.index'));

        // Verify registration attributes
        $this->assertEquals(RegistrationStatus::Confirmed, $registration->status);
        $this->assertMatchesRegularExpression('/^EVENT-REG-[A-Z0-9]{8}$/', $registration->registration_code);

        // Verify quota decrement
        $vipTicket->refresh();
        $this->assertEquals(24, $vipTicket->remainingQuota());

        // Verify storage QR Code SVG creation
        $this->assertNotNull($registration->qr_code_path);
        Storage::disk('public')->assertExists($registration->qr_code_path);

        // ──────────────────────────────────────────────────────────────────────
        // STEP 6: PARTICIPANT DIGITAL TICKET BOARDING PASS
        // ──────────────────────────────────────────────────────────────────────
        $ticketPassResponse = $this->get(route('registrations.show', $registration));
        $ticketPassResponse->assertStatus(200);
        $ticketPassResponse->assertSee($registration->registration_code);
        $ticketPassResponse->assertSee('VIP All-Access');
        $ticketPassResponse->assertSee('Maya Lin');
        $ticketPassResponse->assertSee('Scan QR code at venue entrance');

        // Log out participant
        $this->post('/logout');

        // ──────────────────────────────────────────────────────────────────────
        // STEP 7 & 8: ON-SITE QR CHECK-IN WORKSTATION
        // ──────────────────────────────────────────────────────────────────────
        $this->actingAs($organizer);

        $stationResponse = $this->get(route('organizer.events.check-in.create', $event));
        $stationResponse->assertStatus(200);
        $stationResponse->assertSee('QR Check-in Station');
        $stationResponse->assertSee('EVENT-REG-XXXXXXXX');

        // Submit attendee check-in with registration code
        $checkInResponse = $this->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $registration->registration_code,
        ]);

        $checkInResponse->assertRedirect(route('organizer.events.check-in.create', $event));
        $checkInResponse->assertSessionHas('status');

        // Verify database state after check-in
        $this->assertDatabaseHas('check_ins', [
            'registration_id' => $registration->id,
            'checked_in_by' => $organizer->id,
        ]);

        $registration->refresh();
        $this->assertEquals(RegistrationStatus::Attended, $registration->status);

        // Verify updated station feed
        $stationUpdateResponse = $this->get(route('organizer.events.check-in.create', $event));
        $stationUpdateResponse->assertSee('Maya Lin');
        $stationUpdateResponse->assertSee($registration->registration_code);
        $stationUpdateResponse->assertSee('100% attended');

        // ──────────────────────────────────────────────────────────────────────
        // STEP 9: PREVENT DUPLICATE CHECK-IN
        // ──────────────────────────────────────────────────────────────────────
        $duplicateCheckInResponse = $this->post(route('organizer.events.check-in.store', $event), [
            'registration_code' => $registration->registration_code,
        ]);

        $duplicateCheckInResponse->assertSessionHasErrors('registration_code');
        $this->assertEquals(1, CheckIn::where('registration_id', $registration->id)->count());

        // ──────────────────────────────────────────────────────────────────────
        // STEP 10: ORGANIZER DETAILED REPORTING & ANALYTICS
        // ──────────────────────────────────────────────────────────────────────
        $reportResponse = $this->get(route('organizer.events.reports.show', $event));
        $reportResponse->assertStatus(200);
        $reportResponse->assertSee('100%'); // 1 out of 1 registered attended
        $reportResponse->assertSee('$250.00'); // Total revenue
        $reportResponse->assertSee('VIP All-Access');
        $reportResponse->assertSee('General Admission');

        // Export attendee roster CSV
        $exportResponse = $this->get(route('organizer.events.reports.export-attendees', $event));
        $exportResponse->assertStatus(200);
        $exportResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Maya Lin', $exportResponse->streamedContent());
        $this->assertStringContainsString($registration->registration_code, $exportResponse->streamedContent());

        // Log out organizer
        $this->post('/logout');

        // ──────────────────────────────────────────────────────────────────────
        // STEP 11: ADMIN PLATFORM OVERSIGHT
        // ──────────────────────────────────────────────────────────────────────
        $admin = User::factory()->create([
            'name' => 'System Supervisor',
            'email' => 'supervisor@eventpulse.org',
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin);

        // Admin dashboard aggregates
        $adminDashboardResponse = $this->get(route('admin.dashboard'));
        $adminDashboardResponse->assertStatus(200);
        $adminDashboardResponse->assertSee('International AI & Robotics Conference 2026');
        $adminDashboardResponse->assertSee('Maya Lin');
        $adminDashboardResponse->assertSee('$250.00');

        // Admin registrations oversight
        $adminRegsResponse = $this->get(route('admin.registrations.index'));
        $adminRegsResponse->assertStatus(200);
        $adminRegsResponse->assertSee($registration->registration_code);
        $adminRegsResponse->assertSee('VIP All-Access');
        $adminRegsResponse->assertSee('Checked In');
    }
}
