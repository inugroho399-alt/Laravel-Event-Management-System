<?php

namespace App\Policies;

use App\Models\CheckIn;
use App\Models\Registration;
use App\Models\User;

class CheckInPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isOrganizer();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CheckIn $checkIn): bool
    {
        return $user->id === $checkIn->registration->event->organizer_id
            || $user->id === $checkIn->checked_in_by;
    }

    /**
     * Determine whether the user can create models (perform check-in).
     */
    public function create(User $user, ?Registration $registration = null): bool
    {
        if ($registration !== null) {
            return $user->id === $registration->event->organizer_id;
        }

        return $user->isOrganizer();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CheckIn $checkIn): bool
    {
        return $user->id === $checkIn->registration->event->organizer_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CheckIn $checkIn): bool
    {
        return $user->id === $checkIn->registration->event->organizer_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CheckIn $checkIn): bool
    {
        return $user->id === $checkIn->registration->event->organizer_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CheckIn $checkIn): bool
    {
        return $user->id === $checkIn->registration->event->organizer_id;
    }
}
