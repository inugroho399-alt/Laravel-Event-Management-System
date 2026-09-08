<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\Organizer\EventController as OrganizerEventController;
use App\Http\Controllers\Organizer\RegistrationController as OrganizerRegistrationController;
use App\Http\Controllers\Organizer\TicketTypeController as OrganizerTicketTypeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->name('home');

// Public Event Discovery
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Participant Registrations
Route::middleware('auth')->group(function () {
    Route::post('/events/{event:slug}/register', [RegistrationController::class, 'store'])->name('events.register');
    Route::get('/my-registrations', [RegistrationController::class, 'index'])->name('registrations.index');
    Route::post('/my-registrations/{registration}/cancel', [RegistrationController::class, 'cancel'])->name('registrations.cancel');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Organizer Event, Ticket, & Attendee Management
Route::prefix('organizer')->name('organizer.')->middleware(['auth', 'role:organizer'])->group(function () {
    Route::resource('events', OrganizerEventController::class);
    Route::resource('events.tickets', OrganizerTicketTypeController::class)->parameters(['tickets' => 'ticket']);
    Route::get('events/{event}/registrations', [OrganizerRegistrationController::class, 'index'])->name('events.registrations.index');
});

require __DIR__.'/auth.php';
