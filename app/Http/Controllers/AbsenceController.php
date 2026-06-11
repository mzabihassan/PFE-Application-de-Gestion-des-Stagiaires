<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Intern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbsenceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status'); // 'justified' | 'unjustified'

        $absencesQuery = Absence::query()
            ->with(['intern.user', 'recordedBy'])
            ->latest('date_absence');

        $absenceStats = [
            'total' => (clone $absencesQuery)->count(),
            'unjustified' => (clone $absencesQuery)->where('justified', false)->count(),
        ];

        $absences = $absencesQuery
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->whereHas('intern.user', fn ($u) => $u->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('intern', fn ($i) => $i->where('cin', 'like', "%{$search}%"))
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->when($status === 'justified', fn ($query) => $query->where('justified', true))
            ->when($status === 'unjustified', fn ($query) => $query->where('justified', false))
            ->paginate(12)
            ->withQueryString();

        return view('absences.index', compact('absences', 'absenceStats', 'search', 'status'));
    }

    public function create(): View
    {
        $interns = Intern::query()
            ->where('is_archived', false)
            ->orderBy('cin')
            ->get();

        return view('absences.create', compact('interns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'intern_id' => ['required', 'exists:interns,id'],
            'date_absence' => ['required', 'date_format:d/m/Y'],
            'reason' => ['required', 'string', 'max:255'],
            'justified' => ['nullable', 'boolean'],
        ]);

        $validated['date_absence'] = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['date_absence'])->toDateString();

        Absence::query()->create($validated + [
            'justified' => $request->boolean('justified'),
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('absences.index')->with('success', 'Absence enregistrée.');
    }

    public function edit(Absence $absence): View
    {
        $interns = Intern::query()
            ->where('is_archived', false)
            ->orderBy('cin')
            ->get();

        return view('absences.edit', compact('absence', 'interns'));
    }

    public function update(Request $request, Absence $absence): RedirectResponse
    {
        $validated = $request->validate([
            'intern_id' => ['required', 'exists:interns,id'],
            'date_absence' => ['required', 'date_format:d/m/Y'],
            'reason' => ['required', 'string', 'max:255'],
            'justified' => ['nullable', 'boolean'],
        ]);

        $validated['date_absence'] = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['date_absence'])->toDateString();

        $absence->update($validated + ['justified' => $request->boolean('justified')]);

        return redirect()->route('absences.index')->with('success', 'Absence mise à jour.');
    }

    public function destroy(Absence $absence): RedirectResponse
    {
        $absence->delete();

        return redirect()->route('absences.index')->with('success', 'Absence supprimée.');
    }
}
