<?php

namespace App\Http\Controllers;

use App\Models\Intern;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Carbon\Carbon;

class InternController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search');
        $status = (string) $request->string('status'); // active | completed | no_internship | archived
        $completedCutoff = today()->subDay()->toDateString();

        $interns = Intern::query()
            ->with(['user', 'internships', 'requests'])
            // Les archivés sont masqués sauf demande explicite.
            ->when($status === 'archived',
                fn ($query) => $query->where('is_archived', true),
                fn ($query) => $query->where('is_archived', false))
            ->when($status === 'completed', fn ($query) => $query
                ->whereNotNull('end_date')->where('end_date', '<', $completedCutoff))
            ->when($status === 'active', fn ($query) => $query
                ->where(fn ($sub) => $sub->whereNull('end_date')->orWhere('end_date', '>=', $completedCutoff))
                ->whereHas('internships', fn ($i) => $i->whereNotNull('supervisor_id')))
            ->when($status === 'no_internship', fn ($query) => $query
                ->whereDoesntHave('internships', fn ($i) => $i->whereNotNull('supervisor_id')))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('cin', 'like', "%{$search}%")
                        ->orWhere('school', 'like', "%{$search}%")
                        ->orWhere('specialty', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw('CASE WHEN is_archived = 1 THEN 2 WHEN end_date < ? THEN 1 ELSE 0 END', [$completedCutoff])
            ->orderByDesc('start_date')
            ->paginate(12)
            ->withQueryString();

        return view('interns.index', compact('interns', 'search', 'status'));
    }

    public function supervisorIndex(Request $request): View
    {
        $search = (string) $request->string('search');
        $status = (string) $request->string('status'); // active | completed | no_internship
        $highlightInternId = $request->integer('highlight');
        $completedCutoff = today()->subDay()->toDateString();

        $interns = Intern::query()
            ->with(['user', 'internships', 'requests'])
            ->where('is_archived', false)
            ->whereHas('internships', fn ($query) => $query->where('supervisor_id', $request->user()->id))
            ->when($status === 'completed', fn ($query) => $query
                ->whereNotNull('end_date')->where('end_date', '<', $completedCutoff))
            ->when($status === 'active', fn ($query) => $query
                ->where(fn ($sub) => $sub->whereNull('end_date')->orWhere('end_date', '>=', $completedCutoff))
                ->whereHas('internships', fn ($i) => $i->whereNotNull('supervisor_id')))
            ->when($status === 'no_internship', fn ($query) => $query
                ->whereDoesntHave('internships', fn ($i) => $i->whereNotNull('supervisor_id')))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('cin', 'like', "%{$search}%")
                        ->orWhere('school', 'like', "%{$search}%")
                        ->orWhere('specialty', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw('CASE WHEN end_date < ? THEN 1 ELSE 0 END', [$completedCutoff])
            ->orderByDesc('start_date')
            ->paginate(12)
            ->withQueryString();

        return view('interns.index', compact('interns', 'search', 'status', 'highlightInternId'));
    }

    public function create(): View
    {
        return view('interns.create');
    }

    public function show(Intern $intern): View
    {
        $intern->load([
            'user',
            'absences.recordedBy',
            'internships.tasks.assignedTo',
            'internships.supervisor',
            'internships.responsible',
            'requests',
            'weeklyReports.generatedBy',
        ]);

        $score = $intern->performanceScore();
        $alerts = $intern->smartAlerts();
        $tasks = $intern->evaluationTasks();
        $weeklyReports = $intern->weeklyReports;
        $weekStarts = collect(range(5, 0))
            ->map(fn (int $weeksAgo) => now()->startOfWeek()->subWeeks($weeksAgo));

        $taskCompletionChart = [
            'labels' => $weekStarts->map(fn ($date) => $date->format('d/m'))->all(),
            'values' => $weekStarts
                ->map(fn ($date): int => Task::query()
                    ->where('status', 'termine')
                    ->whereBetween('updated_at', [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()])
                    ->whereHas('internship.interns', fn ($query) => $query->where('interns.id', $intern->id))
                    ->count())
                ->all(),
        ];

        $taskCompletionChart['max'] = max($taskCompletionChart['values'] ?: [0]);

        return view('interns.show', compact('intern', 'score', 'alerts', 'tasks', 'taskCompletionChart', 'weeklyReports'));
    }

    public function supervisorShow(Request $request, Intern $intern): View
    {
        $isAssignedToSupervisor = $intern->internships()
            ->where('supervisor_id', $request->user()->id)
            ->exists();

        if (! $isAssignedToSupervisor) {
            abort(403, 'Accès refusé à ce stagiaire.');
        }

        return $this->show($intern);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'cin' => ['required', 'string', 'max:40', 'unique:interns,cin'],
            'school' => ['required', 'string', 'max:120'],
            'specialty' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['required', 'date_format:d/m/Y'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                return;
            }

            $startDate = Carbon::createFromFormat('d/m/Y', $request->input('start_date'));
            $endDate = Carbon::createFromFormat('d/m/Y', $request->input('end_date'));

            if ($endDate->lt($startDate)) {
                $validator->errors()->add('end_date', 'La date de fin doit être après ou égale à la date de début.');
            }
        });

        $validated = $validator->validated();

        $validated['start_date'] = Carbon::createFromFormat('d/m/Y', $validated['start_date'])->toDateString();
        $validated['end_date'] = Carbon::createFromFormat('d/m/Y', $validated['end_date'])->toDateString();

        $roleId = Role::query()->where('name', 'Stagiaire')->value('id');

        DB::transaction(function () use ($validated, $roleId): void {
            $user = User::query()->create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password_hash' => Hash::make($validated['password']),
                'role_id' => $roleId,
                'is_active' => true,
            ]);

            Intern::query()->create([
                'user_id' => $user->id,
                'cin' => $validated['cin'],
                'school' => $validated['school'],
                'specialty' => $validated['specialty'],
                'phone' => $validated['phone'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_archived' => false,
            ]);
        });

        return redirect()->route('interns.index')->with('success', 'Stagiaire ajouté.');
    }

    public function edit(Intern $intern): View
    {
        $intern->load('user');

        return view('interns.edit', compact('intern'));
    }

    public function update(Request $request, Intern $intern): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($intern->user_id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'cin' => ['required', 'string', 'max:40', Rule::unique('interns', 'cin')->ignore($intern->id)],
            'school' => ['required', 'string', 'max:120'],
            'specialty' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['required', 'date_format:d/m/Y'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                return;
            }

            $startDate = Carbon::createFromFormat('d/m/Y', $request->input('start_date'));
            $endDate = Carbon::createFromFormat('d/m/Y', $request->input('end_date'));

            if ($endDate->lt($startDate)) {
                $validator->errors()->add('end_date', 'La date de fin doit être après ou égale à la date de début.');
            }
        });

        $validated = $validator->validated();

        $validated['start_date'] = Carbon::createFromFormat('d/m/Y', $validated['start_date'])->toDateString();
        $validated['end_date'] = Carbon::createFromFormat('d/m/Y', $validated['end_date'])->toDateString();

        $roleId = Role::query()->where('name', 'Stagiaire')->value('id');

        DB::transaction(function () use ($intern, $validated, $roleId): void {
            $userPayload = [
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'role_id' => $roleId,
                'is_active' => true,
            ];

            if (! empty($validated['password'])) {
                $userPayload['password_hash'] = Hash::make($validated['password']);
            }

            if ($intern->user) {
                $intern->user->update($userPayload);
                $userId = $intern->user->id;
            } else {
                $userPayload['password_hash'] = Hash::make($validated['password'] ?: 'password123');
                $userId = User::query()->create($userPayload)->id;
            }

            $intern->update([
                'user_id' => $userId,
                'cin' => $validated['cin'],
                'school' => $validated['school'],
                'specialty' => $validated['specialty'],
                'phone' => $validated['phone'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);
        });

        return redirect()->route('interns.index')->with('success', 'Stagiaire mis à jour.');
    }

    public function destroy(Intern $intern): RedirectResponse
    {
        $intern->delete();

        return redirect()->route('interns.index')->with('success', 'Stagiaire supprimé.');
    }

    public function archive(Intern $intern): RedirectResponse
    {
        $intern->update(['is_archived' => true]);

        return back()->with('success', 'Stagiaire archivé.');
    }

    public function restore(Intern $intern): RedirectResponse
    {
        $intern->update(['is_archived' => false]);

        return back()->with('success', 'Stagiaire restauré.');
    }
}
