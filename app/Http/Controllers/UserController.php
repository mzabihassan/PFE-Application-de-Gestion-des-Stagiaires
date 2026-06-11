<?php

namespace App\Http\Controllers;

use App\Models\Intern;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search');
        $roleId = (string) $request->string('role');
        $active = $request->query('active'); // '1' | '0'

        $users = User::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($roleId !== '', fn ($query) => $query->where('role_id', $roleId))
            ->when($active === '1', fn ($query) => $query->where('is_active', true))
            ->when($active === '0', fn ($query) => $query->where('is_active', false))
            ->orderBy('full_name')
            ->paginate(12)
            ->withQueryString();

        $roles = Role::query()->orderBy('name')->get();

        return view('users.index', compact('users', 'search', 'roleId', 'active', 'roles'));
    }

    public function create(): View
    {
        $roles = Role::query()->orderBy('name')->get();

        return view('users.create', [
            'roles'     => $roles,
            'actionUrl' => route('users.store'),
            'cancelUrl' => route('users.index'),
        ]);
    }

    /**
     * Show the create form pre-locked to the Stagiaire role (for RC).
     */
    public function createIntern(): View
    {
        $stagiaireRole = Role::query()->where('name', 'Stagiaire')->firstOrFail();

        // Only pass the Stagiaire role so the form is locked to it
        $roles = collect([$stagiaireRole]);

        return view('users.create', [
            'roles'            => $roles,
            'lockRoleToIntern' => true,
            'actionUrl'        => route('interns.store-intern'),
            'cancelUrl'        => route('interns.index'),
        ]);
    }

    /**
     * Store a newly created intern user (for RC).
     */
    public function storeIntern(Request $request): RedirectResponse
    {
        $stagiaireRole = Role::query()->where('name', 'Stagiaire')->firstOrFail();
        
        // Force role_id to be Stagiaire
        $request->merge(['role_id' => $stagiaireRole->id]);

        // Base validation rules
        $rules = [
            'full_name' => ['required', 'string', 'max:120'],
            'email'     => ['required', 'email', 'max:120', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'role_id'   => ['required', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
            'cin'        => ['required', 'string', 'max:40', 'unique:interns,cin'],
            'school'     => ['required', 'string', 'max:120'],
            'specialty'  => ['required', 'string', 'max:120'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date'   => ['required', 'date_format:d/m/Y'],
        ];

        $validated = $request->validate($rules);

        // Validate date ordering
        $startDate = Carbon::createFromFormat('d/m/Y', $validated['start_date']);
        $endDate   = Carbon::createFromFormat('d/m/Y', $validated['end_date']);

        if ($endDate->lt($startDate)) {
            return back()->withErrors(['end_date' => 'La date de fin doit être après ou égale à la date de début.'])->withInput();
        }

        DB::transaction(function () use ($validated, $request) {
            $user = User::query()->create([
                'full_name'     => $validated['full_name'],
                'email'         => $validated['email'],
                'password_hash' => Hash::make($validated['password']),
                'role_id'       => (int) $validated['role_id'],
                'is_active'     => $request->boolean('is_active', true),
            ]);

            Intern::query()->create([
                'user_id'     => $user->id,
                'cin'         => $validated['cin'],
                'school'      => $validated['school'],
                'specialty'   => $validated['specialty'],
                'phone'       => $validated['phone'] ?? null,
                'start_date'  => Carbon::createFromFormat('d/m/Y', $validated['start_date'])->toDateString(),
                'end_date'    => Carbon::createFromFormat('d/m/Y', $validated['end_date'])->toDateString(),
                'is_archived' => false,
            ]);
        });

        return redirect()->route('interns.index')->with('success', 'Stagiaire créé avec succès.');
    }

    public function store(Request $request): RedirectResponse
    {
        $stagiaireRoleId = Role::query()->where('name', 'Stagiaire')->value('id');
        $isIntern = (int) $request->input('role_id') === $stagiaireRoleId;

        // Base validation rules
        $rules = [
            'full_name' => ['required', 'string', 'max:120'],
            'email'     => ['required', 'email', 'max:120', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'role_id'   => ['required', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ];

        // Add intern-specific rules when creating a Stagiaire
        if ($isIntern) {
            $rules['cin']        = ['required', 'string', 'max:40', 'unique:interns,cin'];
            $rules['school']     = ['required', 'string', 'max:120'];
            $rules['specialty']  = ['required', 'string', 'max:120'];
            $rules['phone']      = ['nullable', 'string', 'max:30'];
            $rules['start_date'] = ['required', 'date_format:d/m/Y'];
            $rules['end_date']   = ['required', 'date_format:d/m/Y'];
        }

        $validated = $request->validate($rules);

        // Validate date ordering for interns
        if ($isIntern) {
            $startDate = Carbon::createFromFormat('d/m/Y', $validated['start_date']);
            $endDate   = Carbon::createFromFormat('d/m/Y', $validated['end_date']);

            if ($endDate->lt($startDate)) {
                return back()->withErrors(['end_date' => 'La date de fin doit être après ou égale à la date de début.'])->withInput();
            }
        }

        DB::transaction(function () use ($validated, $isIntern, $request) {
            $user = User::query()->create([
                'full_name'     => $validated['full_name'],
                'email'         => $validated['email'],
                'password_hash' => Hash::make($validated['password']),
                'role_id'       => (int) $validated['role_id'],
                'is_active'     => $request->boolean('is_active', true),
            ]);

            if ($isIntern) {
                Intern::query()->create([
                    'user_id'     => $user->id,
                    'cin'         => $validated['cin'],
                    'school'      => $validated['school'],
                    'specialty'   => $validated['specialty'],
                    'phone'       => $validated['phone'] ?? null,
                    'start_date'  => Carbon::createFromFormat('d/m/Y', $validated['start_date'])->toDateString(),
                    'end_date'    => Carbon::createFromFormat('d/m/Y', $validated['end_date'])->toDateString(),
                    'is_archived' => false,
                ]);
            }
        });

        return redirect()->route('users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $user): View
    {
        $roles = Role::query()->orderBy('name')->get();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $stagiaireRoleId = Role::query()->where('name', 'Stagiaire')->value('id');
        $isIntern = (int) $request->input('role_id') === $stagiaireRoleId;

        // Base validation rules
        $rules = [
            'full_name' => ['required', 'string', 'max:120'],
            'email'     => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['nullable', 'string', 'min:8'],
            'role_id'   => ['required', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ];

        // Add intern-specific rules
        if ($isIntern) {
            $existingIntern = Intern::where('user_id', $user->id)->first();
            $rules['cin']        = ['required', 'string', 'max:40', Rule::unique('interns', 'cin')->ignore($existingIntern?->id)];
            $rules['school']     = ['required', 'string', 'max:120'];
            $rules['specialty']  = ['required', 'string', 'max:120'];
            $rules['phone']      = ['nullable', 'string', 'max:30'];
            $rules['start_date'] = ['required', 'date_format:d/m/Y'];
            $rules['end_date']   = ['required', 'date_format:d/m/Y'];
        }

        $validated = $request->validate($rules);

        // Validate date ordering for interns
        if ($isIntern) {
            $startDate = Carbon::createFromFormat('d/m/Y', $validated['start_date']);
            $endDate   = Carbon::createFromFormat('d/m/Y', $validated['end_date']);

            if ($endDate->lt($startDate)) {
                return back()->withErrors(['end_date' => 'La date de fin doit être après ou égale à la date de début.'])->withInput();
            }
        }

        DB::transaction(function () use ($user, $validated, $isIntern, $request) {
            $payload = [
                'full_name' => $validated['full_name'],
                'email'     => $validated['email'],
                'role_id'   => (int) $validated['role_id'],
                'is_active' => $request->boolean('is_active', false),
            ];

            if (! empty($validated['password'])) {
                $payload['password_hash'] = Hash::make($validated['password']);
            }

            $user->update($payload);

            if ($isIntern) {
                Intern::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'cin'         => $validated['cin'],
                        'school'      => $validated['school'],
                        'specialty'   => $validated['specialty'],
                        'phone'       => $validated['phone'] ?? null,
                        'start_date'  => Carbon::createFromFormat('d/m/Y', $validated['start_date'])->toDateString(),
                        'end_date'    => Carbon::createFromFormat('d/m/Y', $validated['end_date'])->toDateString(),
                        'is_archived' => false,
                    ]
                );
            }
        });

        return redirect()->route('users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Utilisateur supprimé.');
    }
}
