<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the organizer dashboard with aggregated metrics and activity feeds.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Scope all queries: admin sees everything, organizer sees only their events.
        $eventScope = $user->isAdmin()
            ? Event::query()
            : $user->events();

        // ── KPI METRICS ───────────────────────────────────────────────────────

        $eventIds = (clone $eventScope)->pluck('id');

        $totalEvents = $eventIds->count();

        $publishedEvents = (clone $eventScope)
            ->where('status', EventStatus::Published)
            ->count();

        $totalRegistrations = Registration::whereIn('event_id', $eventIds)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->count();

        $totalAttended = Registration::whereIn('event_id', $eventIds)
            ->where('status', RegistrationStatus::Attended)
            ->count();

        // Unique participants: distinct user_ids with non-cancelled registrations
        $totalParticipants = Registration::whereIn('event_id', $eventIds)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->distinct('user_id')
            ->count('user_id');

        // Total ticket types created across organizer's events
        $totalTicketTypes = TicketType::whereIn('event_id', $eventIds)->count();

        // Revenue: sum of ticket_type.price × registrations (confirmed + attended)
        $totalRevenue = Registration::whereIn('registrations.event_id', $eventIds)
            ->whereIn('registrations.status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Attended->value,
            ])
            ->join('ticket_types', 'registrations.ticket_type_id', '=', 'ticket_types.id')
            ->sum('ticket_types.price');

        // Event status breakdown for summary bar
        $eventStatusBreakdown = (clone $eventScope)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // ── UPCOMING & RECENT EVENTS ──────────────────────────────────────────

        $upcomingEvents = (clone $eventScope)
            ->whereIn('status', [EventStatus::Published, EventStatus::Ongoing])
            ->where('start_date', '>=', now())
            ->withCount([
                'registrations as active_registrations_count' => function ($q) {
                    $q->where('status', '!=', RegistrationStatus::Cancelled);
                },
                'registrations as attended_count' => function ($q) {
                    $q->where('status', RegistrationStatus::Attended);
                },
            ])
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        // Recent events: last 5 events by start_date (past or completed)
        $recentEvents = (clone $eventScope)
            ->where('start_date', '<', now())
            ->withCount([
                'registrations as active_registrations_count' => function ($q) {
                    $q->where('status', '!=', RegistrationStatus::Cancelled);
                },
                'registrations as attended_count' => function ($q) {
                    $q->where('status', RegistrationStatus::Attended);
                },
            ])
            ->orderByDesc('start_date')
            ->limit(5)
            ->get();

        // ── RECENT ACTIVITY FEEDS ─────────────────────────────────────────────

        $recentRegistrations = Registration::whereIn('event_id', $eventIds)
            ->with(['user', 'event', 'ticketType'])
            ->latest()
            ->limit(5)
            ->get();

        $recentCheckIns = CheckIn::whereHas('registration', function ($q) use ($eventIds) {
            $q->whereIn('event_id', $eventIds);
        })
            ->with(['registration.user', 'registration.event', 'registration.ticketType', 'checker'])
            ->latest('checked_in_at')
            ->limit(5)
            ->get();

        return view('organizer.dashboard', compact(
            'totalEvents',
            'publishedEvents',
            'totalRegistrations',
            'totalAttended',
            'totalParticipants',
            'totalTicketTypes',
            'totalRevenue',
            'eventStatusBreakdown',
            'upcomingEvents',
            'recentEvents',
            'recentRegistrations',
            'recentCheckIns',
        ));
    }
}
