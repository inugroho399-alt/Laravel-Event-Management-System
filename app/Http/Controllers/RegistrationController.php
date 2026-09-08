<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    /**
     * Display a listing of the participant's registrations.
     */
    public function index(Request $request): View
    {
        $registrations = $request->user()->registrations()
            ->with(['event.organizer', 'ticketType'])
            ->latest()
            ->paginate(10);

        return view('registrations.index', compact('registrations'));
    }

    /**
     * Display the digital ticket for the specified registration.
     */
    public function show(Registration $registration, QrCodeService $qrCodeService): View
    {
        Gate::authorize('view', $registration);

        // Ensure QR code is generated and stored if missing
        if (! $registration->qr_code_path || ! Storage::disk('public')->exists($registration->qr_code_path)) {
            $qrCodeService->generateAndStore($registration);
        }

        $registration->load([
            'event.organizer',
            'ticketType',
            'user',
            'checkIn.checker',
        ]);

        return view('registrations.show', compact('registration'));
    }

    /**
     * Store a newly created registration for the event.
     */
    public function store(StoreRegistrationRequest $request, Event $event, QrCodeService $qrCodeService): RedirectResponse
    {
        if (! $event->isRegistrationOpen()) {
            return back()->withErrors([
                'error' => 'Registration is currently closed for this event.',
            ]);
        }

        $user = $request->user();

        try {
            DB::transaction(function () use ($event, $request, $user, $qrCodeService) {
                // Prevent duplicate active registration for the same event
                $alreadyRegistered = $event->registrations()
                    ->where('user_id', $user->id)
                    ->where('status', '!=', RegistrationStatus::Cancelled->value)
                    ->exists();

                if ($alreadyRegistered) {
                    throw new \DomainException('You have already registered for this event.');
                }

                /** @var TicketType|null $ticket */
                $ticket = TicketType::where('id', $request->ticket_type_id)
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->first();

                if (! $ticket) {
                    throw new \DomainException('Selected ticket type does not belong to this event.');
                }

                if ($ticket->remainingQuota() <= 0) {
                    throw new \DomainException('Selected ticket tier is sold out.');
                }

                $registration = Registration::create([
                    'registration_code' => Registration::generateUniqueCode(),
                    'user_id' => $user->id,
                    'event_id' => $event->id,
                    'ticket_type_id' => $ticket->id,
                    'status' => RegistrationStatus::Confirmed,
                ]);

                $qrCodeService->generateAndStore($registration);
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('registrations.index')
            ->with('status', 'Registration successful! You have secured your ticket.');
    }

    /**
     * Cancel an active registration.
     */
    public function cancel(Registration $registration): RedirectResponse
    {
        Gate::authorize('delete', $registration);

        if ($registration->status === RegistrationStatus::Cancelled) {
            return back()->withErrors(['error' => 'This registration is already cancelled.']);
        }

        $registration->update([
            'status' => RegistrationStatus::Cancelled,
        ]);

        return back()->with('status', 'Registration has been cancelled successfully.');
    }
}
