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
        $totalUsers = User::count();
        $adminUsers = User::where('role', UserRole::Admin)->count();
        $organizerUsers = User::where('role', UserRole::Organizer)->count();
        $participantUsers = User::where('role', UserRole::Participant)->count();

        // ── EVENT STATS ───────────────────────────────────────────────────────
        $totalEvents = Event::count();
        $publishedEvents = Event::where('status', EventStatus::Published)->count();
        $ongoingEvents = Event::where('status', EventStatus::Ongoing)->count();
        $completedEvents = Event::where('status', EventStatus::Completed)->count();
        $draftEvents = Event::where('status', EventStatus::Draft)->count();
        $cancelledEvents = Event::where('status', EventStatus::Cancelled)->count();

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
