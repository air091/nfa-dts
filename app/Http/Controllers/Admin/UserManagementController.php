<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('unit')->latest()->get();
        $units = Unit::where('is_active', true)->get();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'units' => $units,
        ]);
    }

    public function pendingUsers()
    {
        $pendingUsers = User::where('role', 'pending')->latest()->get();
        $units = Unit::where('is_active', true)->get();
        $takenDoUnits = User::query()
            ->where('role', '!=', 'pending')
            ->whereNotNull('unit_id')
            ->with('unit:id,full_name')
            ->get()
            ->filter(fn($user) => str_ends_with(strtoupper((string) ($user->unit->full_name ?? '')), '/DO'))
            ->mapWithKeys(fn($user) => [
                (string) $user->unit_id => [
                    'unit_name' => $user->unit->full_name,
                    'user_name' => $user->name,
                ],
            ])
            ->all();

        return Inertia::render('Admin/PendingUsers', [
            'pendingUsers' => $pendingUsers,
            'units' => $units,
            'takenDoUnits' => $takenDoUnits,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:encoder,viewer,clerk,admin',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        if ($request->filled('unit_id')) {
            $this->ensureDoUnitAvailability((int) $request->unit_id);
        }

        User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'unit_id' => $request->unit_id,
        ]);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        // Handle partial updates (e.g., role-only updates)
        if ($request->has('role') && !$request->has('name')) {
            $validated = $request->validate([
                'role' => 'required|in:encoder,viewer,clerk,admin',
            ]);

            Log::info('Updating user role', [
                'user_id' => $user->id,
                'old_role' => $user->role,
                'new_role' => $validated['role'],
                'request_data' => $request->all()
            ]);

            $user->role = $validated['role'];
            $user->save();

            Log::info('User role updated', [
                'user_id' => $user->id,
                'current_role' => $user->role
            ]);

            return redirect()->back()->with('success', 'User role updated successfully.');
        }

        // Handle full user updates
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:encoder,viewer,clerk,admin',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        if ($request->filled('unit_id')) {
            $this->ensureDoUnitAvailability((int) $request->unit_id, $user->id);
        }

        $user->update([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'role' => $request->role,
            'unit_id' => $request->unit_id,
        ]);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();
        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Password reset successfully.');
    }

    public function approveUser(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:encoder,viewer,clerk',
            'unit_id' => 'required|exists:units,id',
        ]);

        $this->ensureDoUnitAvailability((int) $request->unit_id, $user->id);

        $user->update([
            'role' => $request->role,
            'unit_id' => $request->unit_id,
        ]);

        return redirect()->back()->with('success', 'User approved successfully');
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'users' => 'required|array',
            'users.*.id' => 'required|exists:users,id',
            'users.*.role' => 'required|in:encoder,viewer,clerk',
            'users.*.unit_id' => 'required|exists:units,id',
        ]);

        $requestedUsers = collect($request->users);
        $requestedUnitIds = $requestedUsers->pluck('unit_id')->unique()->values();

        $doUnitIds = Unit::whereIn('id', $requestedUnitIds)
            ->where('full_name', 'like', '%/DO')
            ->pluck('id')
            ->map(fn($id) => (int) $id);

        $duplicateDoUnit = $requestedUsers
            ->whereIn('unit_id', $doUnitIds)
            ->groupBy('unit_id')
            ->first(fn($group) => count($group) > 1);

        if ($duplicateDoUnit) {
            $unitId = (int) $duplicateDoUnit[0]['unit_id'];
            $unitName = Unit::find($unitId)?->full_name ?? 'selected DO unit';
            throw ValidationException::withMessages([
                'users' => "Bulk approval failed: {$unitName} can only have one approved user.",
            ]);
        }

        DB::transaction(function () use ($request) {
            foreach ($request->users as $userData) {
                $user = User::find($userData['id']);
                if (!$user || $user->role !== 'pending') {
                    continue;
                }

                $this->ensureDoUnitAvailability((int) $userData['unit_id'], $user->id);

                $user->update([
                    'role' => $userData['role'],
                    'unit_id' => $userData['unit_id'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Users approved successfully');
    }

    public function bulkReject(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'required|exists:users,id',
        ]);

        User::whereIn('id', $request->user_ids)->delete();

        return redirect()->back()->with('success', 'Users rejected successfully');
    }

    private function ensureDoUnitAvailability(int $unitId, ?int $ignoreUserId = null): void
    {
        $unit = Unit::find($unitId);
        if (!$unit) {
            return;
        }

        $fullName = strtoupper(trim((string) $unit->full_name));
        if (!str_ends_with($fullName, '/DO')) {
            return;
        }

        $existingAssignment = User::query()
            ->where('unit_id', $unitId)
            ->where('role', '!=', 'pending')
            ->when($ignoreUserId, fn($query) => $query->where('id', '!=', $ignoreUserId))
            ->first();

        if (!$existingAssignment) {
            return;
        }

        throw ValidationException::withMessages([
            'unit_id' => "The unit {$unit->full_name} is already assigned to {$existingAssignment->name}.",
        ]);
    }
}
