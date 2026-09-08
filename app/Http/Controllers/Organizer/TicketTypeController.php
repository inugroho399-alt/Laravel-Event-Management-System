<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketTypeRequest;
use App\Http\Requests\UpdateTicketTypeRequest;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TicketTypeController extends Controller
{
    /**
     * Display a listing of ticket types for the event.
     */
    public function index(Event $event): View
    {
        Gate::authorize('update', $event);

        $ticketTypes = $event->ticketTypes()
            ->withCount([
                'registrations as active_registrations_count' => function ($query) {
                    $query->where('status', '!=', RegistrationStatus::Cancelled->value);
                },
            ])
            ->latest()
            ->get();

        $stats = [
            'total_types' => $ticketTypes->count(),
            'total_capacity' => $ticketTypes->sum('quota'),
            'total_registered' => $ticketTypes->sum('active_registrations_count'),
            'total_remaining' => max(0, $ticketTypes->sum('quota') - $ticketTypes->sum('active_registrations_count')),
        ];

        return view('organizer.tickets.index', compact('event', 'ticketTypes', 'stats'));
    }

    /**
     * Show the form for creating a new ticket type.
     */
    public function create(Event $event): View
    {
        Gate::authorize('create', [TicketType::class, $event]);

        return view('organizer.tickets.create', compact('event'));
    }

    /**
     * Store a newly created ticket type in storage.
     */
    public function store(StoreTicketTypeRequest $request, Event $event): RedirectResponse
    {
        $event->ticketTypes()->create($request->validated());

        return redirect()
            ->route('organizer.events.tickets.index', $event)
            ->with('status', 'Ticket type created successfully.');
    }

    /**
     * Show the form for editing the specified ticket type.
     */
    public function edit(Event $event, TicketType $ticket): View
    {
        abort_if($ticket->event_id !== $event->id, 404);

        Gate::authorize('update', $ticket);

        $registeredCount = $ticket->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->count();

        return view('organizer.tickets.edit', compact('event', 'ticket', 'registeredCount'));
    }

    /**
     * Update the specified ticket type in storage.
     */
    public function update(UpdateTicketTypeRequest $request, Event $event, TicketType $ticket): RedirectResponse
    {
        abort_if($ticket->event_id !== $event->id, 404);

        $ticket->update($request->validated());

        return redirect()
            ->route('organizer.events.tickets.index', $event)
            ->with('status', 'Ticket type updated successfully.');
    }

    /**
     * Remove the specified ticket type from storage.
     */
    public function destroy(Event $event, TicketType $ticket): RedirectResponse
    {
        abort_if($ticket->event_id !== $event->id, 404);

        Gate::authorize('delete', $ticket);

        $hasRegistrations = $ticket->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->exists();

        if ($hasRegistrations) {
            return back()->withErrors([
                'error' => 'Cannot delete a ticket type that already has active registrations.',
            ]);
        }

        $ticket->delete();

        return redirect()
            ->route('organizer.events.tickets.index', $event)
            ->with('status', 'Ticket type deleted successfully.');
    }
}
