<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * Display a listing of all events across all organizers.
     */
    public function index(Request $request): View
    {
        $query = Event::query()
            ->with(['organizer', 'ticketTypes'])
            ->withCount(['registrations']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && EventStatus::tryFrom($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('organizer_id')) {
            $query->where('organizer_id', $request->input('organizer_id'));
        }

        $events = $query->latest('start_date')->paginate(15)->withQueryString();

        $organizers = User::whereIn('role', [UserRole::Organizer, UserRole::Admin])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.events.index', [
            'events' => $events,
            'organizers' => $organizers,
            'statuses' => EventStatus::cases(),
            'filters' => $request->only(['search', 'status', 'organizer_id']),
        ]);
    }

    /**
     * Display the specified event.
     */
    public function show(Event $event): RedirectResponse
    {
        return redirect()->route('organizer.events.reports.show', $event);
    }

    /**
     * Update the moderation status of the specified event.
     */
    public function updateStatus(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(EventStatus::class)],
        ]);

        $event->update($validated);

        return back()->with('status', "Event '{$event->title}' status updated to {$event->status->label()}.");
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $title = $event->title;

        if ($event->banner_image && Storage::disk('public')->exists($event->banner_image)) {
            Storage::disk('public')->delete($event->banner_image);
        }

        $event->delete();

        return redirect()->route('admin.events.index')->with('status', "Event '{$title}' has been deleted.");
    }
}
