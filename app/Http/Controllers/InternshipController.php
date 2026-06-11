<?php

namespace App\Http\Controllers;

use App\Models\Intern;
use App\Models\Internship;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InternshipController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->string('status');
        $search = (string) $request->string('search');

        $internships = Internship::query()
            ->with(['interns.user', 'supervisor', 'responsible'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('internships.index', compact('internships', 'status', 'search'));
    }

    public function supervisorIndex(Request $request): View
    {
        $status = (string) $request->string('status');
        $search = (string) $request->string('search');

        $internships = Internship::query()
            ->with(['interns.user'])
            ->where('supervisor_id', $request->user()->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhereHas('interns.user', fn ($u) => $u->where('full_name', 'like', "%{$search}%"));
            }))
            ->orderBy('start_date')
            ->paginate(12)
            ->withQueryString();

        return view('internships.my-interns', compact('internships', 'status', 'search'));
    }

    public function supervisorShow(Request $request, Internship $internship): View
    {
        if ($internship->supervisor_id !== $request->user()->id) {
            abort(403, 'Accès refusé à ce stage.');
        }

        $internship->load(['interns.user', 'responsible', 'supervisor', 'tasks.assignedTo']);

        $tasks = $internship->tasks;
        $totalTasks = $tasks->count();
        $doneTasks = $tasks->where('status', 'termine')->count();
        $openTasks = $tasks->whereIn('status', ['a_faire', 'en_cours'])->count();
        $overdueTasks = $tasks->filter(function ($task) {
            return $task->due_date !== null
                && $task->status !== 'termine'
                && $task->due_date->lt(today());
        })->count();

        $taskStats = compact('totalTasks', 'doneTasks', 'openTasks', 'overdueTasks');

        return view('internships.supervisor-show', compact('internship', 'tasks', 'taskStats'));
    }

    public function supervisorValidate(Request $request, Internship $internship): RedirectResponse
    {
        if ($internship->supervisor_id !== $request->user()->id) {
            abort(403, 'Action non autorisée.');
        }

        $validated = $request->validate([
            'grade' => ['required', 'numeric', 'min:0', 'max:20'],
        ]);

        if ($internship->status !== 'termine') {
            $internship->update([
                'status' => 'termine',
                'grade' => $validated['grade'],
            ]);
        } else {
            $internship->update(['grade' => $validated['grade']]);
        }

        return back()->with('success', 'Fin de stage validée.');
    }

    public function supervisorUndoValidate(Request $request, Internship $internship): RedirectResponse
    {
        if ($internship->supervisor_id !== $request->user()->id) {
            abort(403, 'Action non autorisée.');
        }

        if ($internship->status !== 'termine') {
            abort(403, 'Le stage n est pas terminé.');
        }

        $internship->update(['status' => 'en_cours']);

        return back()->with('success', 'Validation de fin de stage annulée.');
    }

    public function create(): View
    {
        $interns = Intern::query()->where('is_archived', false)->orderBy('cin')->get();

        $supervisors = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Encadrant'))
            ->orderBy('full_name')
            ->get();

        $responsibles = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Responsable de competence'))
            ->orderBy('full_name')
            ->get();

        return view('internships.create', compact('interns', 'supervisors', 'responsibles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'department' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['required', 'date_format:d/m/Y'],
            'status' => ['required', Rule::in(['planifie', 'en_cours', 'termine'])],
            'intern_ids' => ['required', 'array', 'min:1'],
            'intern_ids.*' => ['exists:interns,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                return;
            }

            $start = $request->input('start_date');
            $end = $request->input('end_date');
            $internIds = (array) $request->input('intern_ids', []);

            if ($start && $end) {
                $startDate = Carbon::createFromFormat('d/m/Y', $start);
                $endDate = Carbon::createFromFormat('d/m/Y', $end);

                if ($endDate->lt($startDate)) {
                    $validator->errors()->add('end_date', 'La date de fin doit être après ou égale à la date de début.');
                    return;
                }

                if (! empty($internIds)) {
                    $interns = Intern::query()->whereIn('id', $internIds)->get();

                    foreach ($interns as $intern) {
                        $internStart = $intern->start_date?->copy()->startOfDay();
                        $internEnd = $intern->end_date?->copy()->endOfDay();

                        if ($internStart && $internEnd) {
                            if ($startDate->lt($internStart) || $endDate->gt($internEnd)) {
                                $validator->errors()->add('start_date', 'La période du stage doit être comprise dans la période du stagiaire.');
                                break;
                            }
                        }
                    }
                }
            }
        });

        $validated = $validator->validated();

        $validated['start_date'] = Carbon::createFromFormat('d/m/Y', $validated['start_date'])->toDateString();
        $validated['end_date'] = Carbon::createFromFormat('d/m/Y', $validated['end_date'])->toDateString();

        $internIds = $validated['intern_ids'];
        unset($validated['intern_ids']);

        $internship = Internship::query()->create($validated);
        $internship->interns()->sync($internIds);

        return redirect()->route('internships.index')->with('success', 'Stage créé avec succès.');
    }

    public function edit(Internship $internship): View
    {
        $internship->load('interns');

        $interns = Intern::query()->where('is_archived', false)->orderBy('cin')->get();

        $supervisors = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Encadrant'))
            ->orderBy('full_name')
            ->get();

        $responsibles = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Responsable de competence'))
            ->orderBy('full_name')
            ->get();

        return view('internships.edit', compact('internship', 'interns', 'supervisors', 'responsibles'));
    }

    public function convention(Internship $internship): View
    {
        $internship->load(['interns.user', 'supervisor', 'responsible']);

        return view('internships.convention', compact('internship'));
    }

    public function internConvention(Intern $intern): View
    {
        $internship = $intern->internships()
            ->with(['interns.user', 'supervisor', 'responsible'])
            ->latest('end_date')
            ->first();

        if ($internship === null) {
            abort(404, 'Aucun stage trouvé pour ce stagiaire.');
        }

        return view('internships.convention', compact('internship'));
    }

    public function update(Request $request, Internship $internship): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'department' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['required', 'date_format:d/m/Y'],
            'status' => ['required', Rule::in(['planifie', 'en_cours', 'termine'])],
            'intern_ids' => ['required', 'array', 'min:1'],
            'intern_ids.*' => ['exists:interns,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                return;
            }

            $start = $request->input('start_date');
            $end = $request->input('end_date');
            $internIds = (array) $request->input('intern_ids', []);

            if ($start && $end) {
                $startDate = Carbon::createFromFormat('d/m/Y', $start);
                $endDate = Carbon::createFromFormat('d/m/Y', $end);

                if ($endDate->lt($startDate)) {
                    $validator->errors()->add('end_date', 'La date de fin doit être après ou égale à la date de début.');
                    return;
                }

                if (! empty($internIds)) {
                    $interns = Intern::query()->whereIn('id', $internIds)->get();

                    foreach ($interns as $intern) {
                        $internStart = $intern->start_date?->copy()->startOfDay();
                        $internEnd = $intern->end_date?->copy()->endOfDay();

                        if ($internStart && $internEnd) {
                            if ($startDate->lt($internStart) || $endDate->gt($internEnd)) {
                                $validator->errors()->add('start_date', 'La période du stage doit être comprise dans la période du stagiaire.');
                                break;
                            }
                        }
                    }
                }
            }
        });

        $validated = $validator->validated();

        $validated['start_date'] = Carbon::createFromFormat('d/m/Y', $validated['start_date'])->toDateString();
        $validated['end_date'] = Carbon::createFromFormat('d/m/Y', $validated['end_date'])->toDateString();

        $internIds = $validated['intern_ids'];
        unset($validated['intern_ids']);

        $internship->update($validated);
        $internship->interns()->sync($internIds);

        return redirect()->route('internships.index')->with('success', 'Stage mis à jour.');
    }

    public function destroy(Internship $internship): RedirectResponse
    {
        if (auth()->user()?->hasRole('Responsable RH')) {
            abort(403, 'Le RH ne peut pas supprimer un stage.');
        }

        $internship->delete();

        return redirect()->route('internships.index')->with('success', 'Stage supprimé.');
    }

    public function updateStatus(Request $request, Internship $internship): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['planifie', 'en_cours', 'termine'])],
        ]);

        $internship->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Statut du stage mis à jour.']);
    }
}
