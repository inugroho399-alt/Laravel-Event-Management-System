<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessCheckInRequest;
use App\Models\CheckIn;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckInController extends Controller
{
    /**
     * Show the check-in workstation interface for the event.
     */
    public function create(Event $event): View
    {
        Gate::authorize('update', $event);

        $totalRegistered = $event->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->count();

        $checkedInCount = $event->registrations()
            ->has('checkIn')
            ->count();

        $pendingCount = max(0, $totalRegistered - $checkedInCount);
        $percentage = $totalRegistered > 0 ? (int) round(($checkedInCount / $totalRegistered) * 100) : 0;

        $recentCheckIns = CheckIn::whereHas('registration', function ($q) use ($event) {
            $q->where('event_id', $event->id);
        })
            ->with(['registration.user', 'registration.ticketType', 'checker'])
            ->latest('checked_in_at')
            ->limit(10)
            ->get();

        return view('organizer.check-ins.create', compact(
            'event',
            'totalRegistered',
            'checkedInCount',
            'pendingCount',
            'percentage',
            'recentCheckIns'
        ));
    }

    /**
     * Process a check-in request using the registration code.
     */
    public function store(ProcessCheckInRequest $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $event);

        // 1. Check event status
        if ($event->status === EventStatus::Draft || $event->status === EventStatus::Cancelled || $event->status === EventStatus::Completed) {
            $errorMessage = 'This event is currently '.strtolower($event->status->value).' and not open for attendee check-in.';

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 422);
            }

            return back()->withErrors(['registration_code' => $errorMessage]);
        }

        // 2. Find registration scoped to this event
        $registration = $event->registrations()
            ->where('registration_code', $request->registration_code)
            ->with(['user', 'ticketType', 'checkIn'])
            ->first();

        if (! $registration) {
            $errorMessage = 'Registration not found for code: '.$request->registration_code.' under this event.';

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 404);
            }

            return back()->withErrors(['registration_code' => $errorMessage]);
        }

        // 3. Validate registration status
        if ($registration->status === RegistrationStatus::Cancelled) {
            $errorMessage = 'This registration was cancelled and cannot be checked in.';

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 422);
            }

            return back()->withErrors(['registration_code' => $errorMessage]);
        }

        // 4. Check whether participant is already checked in
        if ($registration->isCheckedIn()) {
            $checkedInTime = $registration->checkIn->checked_in_at->format('M d, Y • h:i A');
            $errorMessage = 'Attendee already checked in on '.$checkedInTime.'.';

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'already_checked_in' => true,
                    'checked_in_at' => $checkedInTime,
                ], 422);
            }

            return back()->withErrors(['registration_code' => $errorMessage]);
        }

        // 5. Create check-in record and update registration status transactionally
        $checkIn = DB::transaction(function () use ($registration, $event) {
            $checkIn = CheckIn::create([
                'registration_id' => $registration->id,
                'checked_in_by' => Auth::id(),
                'checked_in_at' => now(),
            ]);

            $registration->update([
                'status' => RegistrationStatus::Attended,
            ]);

            Log::info("Check-in successful: Registration {$registration->registration_code} for event {$event->id} by staff user ".Auth::id());

            return $checkIn;
        });

        // 6. Return success result
        $successMessage = 'Check-in successful! Welcome '.$registration->user->name.' ('.$registration->ticketType->name.').';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'attendee' => [
                    'name' => $registration->user->name,
                    'email' => $registration->user->email,
                    'ticket_tier' => $registration->ticketType->name,
                    'registration_code' => $registration->registration_code,
                    'checked_in_at' => $checkIn->checked_in_at->format('M d, Y • h:i A'),
                ],
            ]);
        }

        return back()->with('status', $successMessage);
    }
}
