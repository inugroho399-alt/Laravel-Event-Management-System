<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    /**
     * Display a listing of all registrations across the entire platform.
     */
    public function index(Request $request): View
    {
        $query = Registration::query()
            ->with(['user', 'event', 'ticketType', 'checkIn.checker']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('registration_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('event', function ($eq) use ($search) {
                        $eq->where('title', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && RegistrationStatus::tryFrom($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        $registrations = $query->latest()->paginate(15)->withQueryString();

        return view('admin.registrations.index', [
            'registrations' => $registrations,
            'statuses' => RegistrationStatus::cases(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
