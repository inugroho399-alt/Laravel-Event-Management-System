<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * Display a listing of the organizer's events.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = $user->isAdmin()
            ? Event::query()->with('organizer')
            : $user->events();

        $events = $query->withCount(['ticketTypes', 'registrations'])
            ->latest('start_date')
            ->paginate(10);

        return view('organizer.events.index', compact('events'));
    }

    /**
     * Show the form for creating a new event.
     */
    public function create(): View
    {
        Gate::authorize('create', Event::class);

        return view('organizer.events.create', [
            'statuses' => EventStatus::cases(),
        ]);
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $slug = Str::slug($data['title']);
        $uniqueSlug = $slug;
        $counter = 1;
        while (Event::where('slug', $uniqueSlug)->exists()) {
            $uniqueSlug = "{$slug}-{$counter}";
            $counter++;
        }
        $data['slug'] = $uniqueSlug;

        if ($request->hasFile('banner_image')) {
            $data['banner_image'] = $request->file('banner_image')->store('banners', 'public');
        }

        $data['organizer_id'] = $request->user()->id;

        Event::create($data);

        return redirect()->route('organizer.events.index')
            ->with('status', 'Event created successfully.');
    }

    /**
     * Show the form for editing the specified event.
     */
    public function edit(Event $event): View
    {
        Gate::authorize('update', $event);

        return view('organizer.events.edit', [
            'event' => $event,
            'statuses' => EventStatus::cases(),
        ]);
    }

    /**
     * Update the specified event in storage.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        $data = $request->validated();

        if ($request->hasFile('banner_image')) {
            if ($event->banner_image) {
                Storage::disk('public')->delete($event->banner_image);
            }
            $data['banner_image'] = $request->file('banner_image')->store('banners', 'public');
        }

        $event->update($data);

        return redirect()->route('organizer.events.index')
            ->with('status', 'Event updated successfully.');
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        if ($event->banner_image) {
            Storage::disk('public')->delete($event->banner_image);
        }

        $event->delete();

        return redirect()->route('organizer.events.index')
            ->with('status', 'Event deleted successfully.');
    }
}
