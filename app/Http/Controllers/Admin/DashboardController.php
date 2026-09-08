<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the system-wide admin oversight dashboard.
     */
    public function index(): View
    {
        // ── USER STATS ────────────────────────────────────────────────────────
        // Single GROUP BY query replaces 4 individual COUNT queries.
        $userCounts = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $totalUsers = (int) $userCounts->sum();
        $adminUsers = (int) $userCounts->get(UserRole::Admin->value, 0);
        $organizerUsers = (int) $userCounts->get(UserRole::Organizer->value, 0);
        $participantUsers = (int) $userCounts->get(UserRole::Participant->value, 0);

        // ── EVENT STATS ───────────────────────────────────────────────────────
        // Single GROUP BY query replaces 6 individual COUNT queries.
        $eventCounts = Event::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalEvents = (int) $eventCounts->sum();
        $publishedEvents = (int) $eventCounts->get(EventStatus::Published->value, 0);
        $ongoingEvents = (int) $eventCounts->get(EventStatus::Ongoing->value, 0);
        $completedEvents = (int) $eventCounts->get(EventStatus::Completed->value, 0);
        $draftEvents = (int) $eventCounts->get(EventStatus::Draft->value, 0);
        $cancelledEvents = (int) $eventCounts->get(EventStatus::Cancelled->value, 0);

        // ── REGISTRATION & CHECK-IN STATS ─────────────────────────────────────
        $totalRegistrations = Registration::where('status', '!=', RegistrationStatus::Cancelled)->count();
        $confirmedRegistrations = Registration::where('status', RegistrationStatus::Confirmed)->count();
        $attendedRegistrations = Registration::where('status', RegistrationStatus::Attended)->count();
        $cancelledRegistrations = Registration::where('status', RegistrationStatus::Cancelled)->count();

        $totalCheckIns = CheckIn::count();

        // ── FINANCIAL & CAPACITY METRICS ──────────────────────────────────────
        $totalRevenue = (float) Registration::whereIn('registrations.status', [
            RegistrationStatus::Confirmed->value,
            RegistrationStatus::Attended->value,
        ])
            ->join('ticket_types', 'registrations.ticket_type_id', '=', 'ticket_types.id')
            ->sum('ticket_types.price');

        $totalCapacity = (int) TicketType::sum('quota');

        $capacityUtilization = $totalCapacity > 0
            ? round(($totalRegistrations / $totalCapacity) * 100, 1)
            : 0;

        $overallAttendanceRate = $totalRegistrations > 0
            ? round(($attendedRegistrations / $totalRegistrations) * 100, 1)
            : 0;

        // ── ACTIVITY FEEDS ────────────────────────────────────────────────────
        $recentUsers = User::latest()->limit(5)->get();

        $recentEvents = Event::with('organizer')
            ->latest()
            ->limit(5)
            ->get();

        $recentRegistrations = Registration::with(['user', 'event', 'ticketType'])
            ->latest()
            ->limit(5)
            ->get();

        $recentCheckIns = CheckIn::with(['registration.user', 'registration.event', 'checker'])
            ->latest('checked_in_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'adminUsers',
            'organizerUsers',
            'participantUsers',
            'totalEvents',
            'publishedEvents',
            'ongoingEvents',
            'completedEvents',
            'draftEvents',
            'cancelledEvents',
            'totalRegistrations',
            'confirmedRegistrations',
            'attendedRegistrations',
            'cancelledRegistrations',
            'totalCheckIns',
            'totalRevenue',
            'totalCapacity',
            'capacityUtilization',
            'overallAttendanceRate',
            'recentUsers',
            'recentEvents',
            'recentRegistrations',
            'recentCheckIns',
        ));
    }
}
