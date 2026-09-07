<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * Display a listing of published events.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim();

        $events = Event::published()
            ->with(['organizer', 'ticketTypes'])
            ->when($search->isNotEmpty(), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest('start_date')
            ->paginate(9)
            ->withQueryString();

        return view('events.index', compact('events', 'search'));
    }

    /**
     * Display the specified event.
     */
    public function show(Event $event): View
    {
        Gate::authorize('view', $event);

        $event->load(['organizer', 'ticketTypes']);

        return view('events.show', compact('event'));
    }
}
