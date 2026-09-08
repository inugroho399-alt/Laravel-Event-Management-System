<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display the comprehensive reporting dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // 1. Base Event Query Scoping
        $baseEventQuery = $user->isAdmin()
            ? Event::query()
            : $user->events();

        // 2. Apply Filters
        $eventsQuery = clone $baseEventQuery;

        if ($request->filled('date_from')) {
            $eventsQuery->where('start_date', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $eventsQuery->where('start_date', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
        }

        if ($request->filled('status') && EventStatus::tryFrom($request->input('status'))) {
            $eventsQuery->where('status', $request->input('status'));
        }

        if ($request->filled('event_id')) {
            $eventsQuery->where('id', $request->input('event_id'));
        }

        $filteredEventIds = (clone $eventsQuery)->pluck('id');

        // 3. Aggregate KPIs
        $totalEvents = $filteredEventIds->count();

        // Capacity: sum of all ticket quotas for filtered events
        $totalCapacity = TicketType::whereIn('event_id', $filteredEventIds)->sum('quota');

        // Active registrations (confirmed or attended)
        $totalRegistrations = Registration::whereIn('event_id', $filteredEventIds)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->count();

        // Attended registrations
        $totalAttended = Registration::whereIn('event_id', $filteredEventIds)
            ->where('status', RegistrationStatus::Attended)
            ->count();

        // Attendance rate
        $overallAttendanceRate = $totalRegistrations > 0
            ? round(($totalAttended / $totalRegistrations) * 100, 1)
            : 0;

        // Total Revenue: sum of ticket prices for confirmed + attended registrations
        $totalRevenue = (float) Registration::whereIn('registrations.event_id', $filteredEventIds)
            ->whereIn('registrations.status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Attended->value,
            ])
            ->join('ticket_types', 'registrations.ticket_type_id', '=', 'ticket_types.id')
            ->sum('ticket_types.price');

        // Capacity Utilization Rate
        $capacityUtilization = $totalCapacity > 0
            ? round(($totalRegistrations / $totalCapacity) * 100, 1)
            : 0;

        // 4. Per-Event Performance Breakdown
        $events = (clone $eventsQuery)
            ->with(['ticketTypes'])
            ->withCount([
                'registrations as active_registrations_count' => function ($q) {
                    $q->where('status', '!=', RegistrationStatus::Cancelled);
                },
                'registrations as attended_count' => function ($q) {
                    $q->where('status', RegistrationStatus::Attended);
                },
                'registrations as cancelled_count' => function ($q) {
                    $q->where('status', RegistrationStatus::Cancelled);
                },
            ])
            ->latest('start_date')
            ->paginate(15)
            ->withQueryString();

        // Calculate per-event revenue and capacity
        $events->getCollection()->transform(function ($event) {
            $event->total_capacity = $event->ticketTypes->sum('quota');
            $event->revenue = (float) Registration::where('registrations.event_id', $event->id)
                ->whereIn('registrations.status', [
                    RegistrationStatus::Confirmed->value,
                    RegistrationStatus::Attended->value,
                ])
                ->join('ticket_types', 'registrations.ticket_type_id', '=', 'ticket_types.id')
                ->sum('ticket_types.price');

            $event->attendance_rate = $event->active_registrations_count > 0
                ? round(($event->attended_count / $event->active_registrations_count) * 100, 1)
                : 0;

            return $event;
        });

        // 5. Ticket Tier Performance Breakdown
        $ticketTiers = TicketType::whereIn('ticket_types.event_id', $filteredEventIds)
            ->with('event')
            ->withCount([
                'registrations as active_registrations_count' => function ($q) {
                    $q->where('status', '!=', RegistrationStatus::Cancelled);
                },
                'registrations as attended_count' => function ($q) {
                    $q->where('status', RegistrationStatus::Attended);
                },
            ])
            ->orderBy('event_id')
            ->get()
            ->map(function ($tier) {
                $tier->revenue = (float) ($tier->price * $tier->active_registrations_count);
                $tier->remaining = max(0, $tier->quota - $tier->active_registrations_count);
                $tier->sold_percentage = $tier->quota > 0
                    ? round(($tier->active_registrations_count / $tier->quota) * 100, 1)
                    : 0;

                return $tier;
            });

        // Event dropdown list for filtering
        $allEventsList = (clone $baseEventQuery)
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('organizer.reports.index', [
            'totalEvents' => $totalEvents,
            'totalCapacity' => $totalCapacity,
            'totalRegistrations' => $totalRegistrations,
            'totalAttended' => $totalAttended,
            'overallAttendanceRate' => $overallAttendanceRate,
            'totalRevenue' => $totalRevenue,
            'capacityUtilization' => $capacityUtilization,
            'events' => $events,
            'ticketTiers' => $ticketTiers,
            'allEventsList' => $allEventsList,
            'statuses' => EventStatus::cases(),
            'filters' => $request->only(['date_from', 'date_to', 'status', 'event_id']),
        ]);
    }

    /**
     * Display a detailed report for a specific event.
     */
    public function show(Event $event): View
    {
        Gate::authorize('update', $event);

        $event->load(['ticketTypes']);

        // Capacities
        $totalCapacity = $event->ticketTypes->sum('quota');

        // Registrations by Status
        $confirmedCount = $event->registrations()->where('status', RegistrationStatus::Confirmed)->count();
        $attendedCount = $event->registrations()->where('status', RegistrationStatus::Attended)->count();
        $cancelledCount = $event->registrations()->where('status', RegistrationStatus::Cancelled)->count();
        $activeRegistrationsCount = $confirmedCount + $attendedCount;
        $totalRegistrationsCount = $activeRegistrationsCount + $cancelledCount;

        // Attendance Rate
        $attendanceRate = $activeRegistrationsCount > 0
            ? round(($attendedCount / $activeRegistrationsCount) * 100, 1)
            : 0;

        // Revenue
        $totalRevenue = (float) Registration::where('registrations.event_id', $event->id)
            ->whereIn('registrations.status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Attended->value,
            ])
            ->join('ticket_types', 'registrations.ticket_type_id', '=', 'ticket_types.id')
            ->sum('ticket_types.price');

        // Capacity Utilization
        $capacityUtilization = $totalCapacity > 0
            ? round(($activeRegistrationsCount / $totalCapacity) * 100, 1)
            : 0;

        // Ticket Tier Breakdown
        $ticketTiers = $event->ticketTypes->map(function ($tier) use ($event) {
            $sold = Registration::where('event_id', $event->id)
                ->where('ticket_type_id', $tier->id)
                ->where('status', '!=', RegistrationStatus::Cancelled)
                ->count();

            $attended = Registration::where('event_id', $event->id)
                ->where('ticket_type_id', $tier->id)
                ->where('status', RegistrationStatus::Attended)
                ->count();

            $revenue = (float) ($tier->price * $sold);
            $remaining = max(0, $tier->quota - $sold);

            return (object) [
                'id' => $tier->id,
                'name' => $tier->name,
                'price' => $tier->price,
                'quota' => $tier->quota,
                'sold' => $sold,
                'remaining' => $remaining,
                'attended' => $attended,
                'revenue' => $revenue,
                'attendance_rate' => $sold > 0 ? round(($attended / $sold) * 100, 1) : 0,
            ];
        });

        // Hourly Check-in Distribution (driver-aware)
        $driver = DB::connection()->getDriverName();
        $hourExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H:00', checked_in_at)",
            'pgsql' => "to_char(checked_in_at, 'YYYY-MM-DD HH24:00')",
            default => "DATE_FORMAT(checked_in_at, '%Y-%m-%d %H:00')",
        };

        $checkIns = CheckIn::whereHas('registration', function ($q) use ($event) {
            $q->where('event_id', $event->id);
        })
            ->select(DB::raw("{$hourExpr} as hour_bucket"), DB::raw('count(*) as count'))
            ->groupBy('hour_bucket')
            ->orderBy('hour_bucket')
            ->get();

        return view('organizer.reports.show', [
            'event' => $event,
            'totalCapacity' => $totalCapacity,
            'confirmedCount' => $confirmedCount,
            'attendedCount' => $attendedCount,
            'cancelledCount' => $cancelledCount,
            'activeRegistrationsCount' => $activeRegistrationsCount,
            'totalRegistrationsCount' => $totalRegistrationsCount,
            'attendanceRate' => $attendanceRate,
            'totalRevenue' => $totalRevenue,
            'capacityUtilization' => $capacityUtilization,
            'ticketTiers' => $ticketTiers,
            'checkInTimeline' => $checkIns,
        ]);
    }

    /**
     * Export attendees for a specific event as a sanitized CSV.
     */
    public function exportEventAttendees(Event $event): StreamedResponse
    {
        Gate::authorize('update', $event);

        $filename = sprintf('event-%s-attendees-%s.csv', $event->slug, now()->format('Ymd-His'));

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($event) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($handle, [
                'Registration Code',
                'Attendee Name',
                'Attendee Email',
                'Ticket Tier',
                'Ticket Price',
                'Status',
                'Registered At',
                'Checked In At',
                'Checked In By',
            ]);

            $event->registrations()
                ->with(['user', 'ticketType', 'checkIn.checker'])
                ->orderBy('created_at')
                ->chunk(100, function ($registrations) use ($handle) {
                    foreach ($registrations as $reg) {
                        fputcsv($handle, [
                            $this->sanitizeCsvValue($reg->registration_code),
                            $this->sanitizeCsvValue($reg->user?->name),
                            $this->sanitizeCsvValue($reg->user?->email),
                            $this->sanitizeCsvValue($reg->ticketType?->name),
                            $reg->ticketType?->price ? number_format($reg->ticketType->price, 2, '.', '') : '0.00',
                            $reg->status->label(),
                            $reg->created_at ? $reg->created_at->format('Y-m-d H:i:s') : '',
                            $reg->checkIn?->checked_in_at ? $reg->checkIn->checked_in_at->format('Y-m-d H:i:s') : 'N/A',
                            $this->sanitizeCsvValue($reg->checkIn?->checker?->name ?? 'N/A'),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export events performance summary as a sanitized CSV.
     */
    public function exportSummary(Request $request): StreamedResponse
    {
        $user = $request->user();

        $query = $user->isAdmin()
            ? Event::query()
            : $user->events();

        if ($request->filled('date_from')) {
            $query->where('start_date', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('start_date', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
        }

        if ($request->filled('status') && EventStatus::tryFrom($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        $filename = sprintf('events-summary-report-%s.csv', now()->format('Ymd-His'));

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Event Title',
                'Status',
                'Start Date',
                'End Date',
                'Location',
                'Total Capacity',
                'Active Registrations',
                'Attended',
                'Attendance Rate (%)',
                'Total Revenue',
            ]);

            $query->with(['ticketTypes'])
                ->withCount([
                    'registrations as active_registrations_count' => function ($q) {
                        $q->where('status', '!=', RegistrationStatus::Cancelled);
                    },
                    'registrations as attended_count' => function ($q) {
                        $q->where('status', RegistrationStatus::Attended);
                    },
                ])
                ->latest('start_date')
                ->chunk(100, function ($events) use ($handle) {
                    foreach ($events as $event) {
                        $capacity = $event->ticketTypes->sum('quota');

                        $revenue = (float) Registration::where('registrations.event_id', $event->id)
                            ->whereIn('registrations.status', [
                                RegistrationStatus::Confirmed->value,
                                RegistrationStatus::Attended->value,
                            ])
                            ->join('ticket_types', 'registrations.ticket_type_id', '=', 'ticket_types.id')
                            ->sum('ticket_types.price');

                        $attendanceRate = $event->active_registrations_count > 0
                            ? round(($event->attended_count / $event->active_registrations_count) * 100, 1)
                            : 0;

                        fputcsv($handle, [
                            $this->sanitizeCsvValue($event->title),
                            $event->status->label(),
                            $event->start_date ? $event->start_date->format('Y-m-d H:i') : '',
                            $event->end_date ? $event->end_date->format('Y-m-d H:i') : '',
                            $this->sanitizeCsvValue($event->location ?: 'Online'),
                            $capacity,
                            $event->active_registrations_count,
                            $event->attended_count,
                            $attendanceRate.'%',
                            number_format($revenue, 2, '.', ''),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Prevent CSV Formula Injection by prefixing potentially dangerous formula starters.
     */
    private function sanitizeCsvValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // If string starts with =, +, -, @, \t, or \r, prefix with single quote to prevent spreadsheet execution
        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
