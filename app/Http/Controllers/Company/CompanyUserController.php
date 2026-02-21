<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CompanyUserController extends Controller
{
    public function __construct(
        protected RegistrationService $registrationService
    ) {}

    /**
     * List all users in the current company.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $users = User::forCompany($user->company_id)
            ->with('roles')
            ->orderBy('name')
            ->paginate(20);

        return view('company.users.index', compact('users'));
    }

    /**
     * Show the user creation form.
     */
    public function create()
    {
        $roles = Role::where('guard_name', 'web')
            ->whereIn('name', ['Company Manager', 'Telemarketer', 'SMS Operator', 'Viewer'])
            ->get();

        return view('company.users.create', compact('roles'));
    }

    /**
     * Store a new user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = $request->user();

        $this->registrationService->inviteUserToCompany(
            $user->company_id,
            $request->only(['name', 'email', 'password']),
            $request->role
        );

        return redirect()->route('company.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show the user edit form.
     */
    public function edit(User $user)
    {
        $this->authorizeCompanyUser($user);

        $roles = Role::where('guard_name', 'web')
            ->whereIn('name', ['Company Owner', 'Company Manager', 'Telemarketer', 'SMS Operator', 'Viewer'])
            ->get();

        return view('company.users.edit', compact('user', 'roles'));
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $targetUser)
    {
        $this->authorizeCompanyUser($targetUser);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $targetUser->id,
            'role' => 'required|string|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $targetUser->update([
            'name' => $request->name,
            'email' => $request->email,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $targetUser->syncRoles([$request->role]);

        return redirect()->route('company.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Toggle user active status.
     */
    public function toggle(User $user)
    {
        $this->authorizeCompanyUser($user);

        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "User {$status} successfully.");
    }

    protected function authorizeCompanyUser(User $targetUser): void
    {
        if ($targetUser->company_id !== auth()->user()->company_id) {
            abort(403);
        }
    }
}
