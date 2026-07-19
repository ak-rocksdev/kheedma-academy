<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /** Staff accounts (admin + mentor), searchable, with an optional role filter. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:admin,mentor'],
        ]);

        $users = User::query()
            ->with('roles:id,name')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'mentor']))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $request->string('role'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->row($u));

        return response()->json(['data' => $users]);
    }

    /** Create a staff account; auto-generates a 6-digit PIN when none is supplied. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:admin,mentor'],
            'password' => ['nullable', 'digits:6'],
        ]);

        $supplied = filled($data['password'] ?? null);
        $plain = $supplied ? $data['password'] : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($plain),
            'is_active' => true,
        ]);
        $user->syncRoles([$data['role']]);

        return response()->json([
            'user' => $this->row($user),
            'generated_password' => $supplied ? null : $plain,
        ], 201);
    }

    /** Edit profile, role, password, or active status (with safety guards). */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => ['sometimes', 'in:admin,mentor'],
            'password' => ['sometimes', 'nullable', 'digits:6'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->guardSelfAndLastAdmin($request, $user, $data);

        foreach (['name', 'email', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }
        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }
        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if (array_key_exists('role', $data)) {
            $user->syncRoles([$data['role']]);
        }

        return response()->json(['user' => $this->row($user->fresh())]);
    }

    /** Delete a staff account (never yourself, never the last active admin). */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'Tidak bisa menghapus akun sendiri.']);
        }
        if ($this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages(['user' => 'Minimal satu admin aktif harus tersisa.']);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    /** Reject edits that would lock out the acting admin or empty the admin pool. */
    private function guardSelfAndLastAdmin(Request $request, User $user, array $data): void
    {
        $isSelf = $request->user()->is($user);
        $deactivating = array_key_exists('is_active', $data) && $data['is_active'] === false;
        $demoting = array_key_exists('role', $data) && $data['role'] !== 'admin' && $user->hasRole('admin');

        if ($isSelf && $deactivating) {
            throw ValidationException::withMessages(['is_active' => 'Tidak bisa menonaktifkan akun sendiri.']);
        }
        if ($isSelf && $demoting) {
            throw ValidationException::withMessages(['role' => 'Tidak bisa menurunkan peran akun sendiri.']);
        }
        if (($deactivating || $demoting) && $this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages(['role' => 'Minimal satu admin aktif harus tersisa.']);
        }
    }

    /** True when $user is an active admin and no other active admin remains. */
    private function isLastActiveAdmin(User $user): bool
    {
        if (! $user->hasRole('admin') || ! $user->is_active) {
            return false;
        }

        return User::role('admin')
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }

    /**
     * @return array{id:int,name:string,email:string,phone:?string,role:?string,is_active:bool}
     */
    private function row(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->getRoleNames()->first(),
            'is_active' => (bool) $u->is_active,
        ];
    }
}
