<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    /**
     * Display a listing of registrations/attendees for the event.
     */
    public function index(Event $event): View
    {
        Gate::authorize('update', $event);

        $registrations = $event->registrations()
            ->with(['user', 'ticketType', 'checkIn.checker'])
            ->latest()
            ->paginate(20);

        $stats = [
            'total' => $event->registrations()->count(),
            'confirmed' => $event->registrations()->where('status', RegistrationStatus::Confirmed)->count(),
            'cancelled' => $event->registrations()->where('status', RegistrationStatus::Cancelled)->count(),
            'attended' => $event->registrations()->where('status', RegistrationStatus::Attended)->count(),
        ];

        return view('organizer.registrations.index', compact('event', 'registrations', 'stats'));
    }
}
