<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of all system users.
     */
    public function index(Request $request): View
    {
        $query = User::query()
            ->withCount(['events', 'registrations']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && UserRole::tryFrom($request->input('role'))) {
            $query->where('role', $request->input('role'));
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', new Enum(UserRole::class)],
        ]);

        // Prevent self-demotion
        if ($user->id === $request->user()->id && $validated['role'] !== UserRole::Admin->value) {
            return back()->withErrors(['role' => 'You cannot demote your own administrator account.']);
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('status', "User '{$user->name}' updated successfully.");
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot delete your own administrator account.']);
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "User '{$userName}' deleted successfully.");
    }
}
