@extends('layouts.app')

@section('title', 'Absences')

@section('content')
@php $isHr = auth()->user()->hasRole('Responsable RH'); @endphp

<x-ui.page-header title="Suivi des absences" kicker="Absences" kicker-icon="bi-calendar-x-fill">
    @unless($isHr)
        <x-slot:actions>
            <a href="{{ route('absences.create') }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> Nouvelle absence</a>
        </x-slot:actions>
    @endunless
</x-ui.page-header>

<div class="card card-soft fade-in">
    <div class="card-body">

        @if($isHr)
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <div class="border rounded p-3 bg-light">
                        <div class="text-muted">Nombre absences</div>
                        <div class="display-6 fw-semibold">{{ $absenceStats['total'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="border rounded p-3 bg-light">
                        <div class="text-muted">Absences non justifiées</div>
                        <div class="display-6 fw-semibold">{{ $absenceStats['unjustified'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Recherche & filtres ──────────────────────────────────── --}}
        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (stagiaire, CIN, motif)">
            <select name="status" class="toolbar-select" data-autosubmit aria-label="Filtrer par justification">
                <option value="">Toutes</option>
                <option value="unjustified" @selected($status === 'unjustified')>Non justifiées</option>
                <option value="justified" @selected($status === 'justified')>Justifiées</option>
            </select>
        </x-ui.table-toolbar>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Stagiaire</th>
                        <th>Date</th>
                        <th>Motif</th>
                        <th>Justifiée</th>
                        <th>Saisie par</th>
                        @unless($isHr)
                            <th class="text-end">Actions</th>
                        @endunless
                    </tr>
                </thead>
                <tbody>
                    @forelse($absences as $absence)
                        <tr>
                            <td>{{ $absence->intern->user?->full_name ?? $absence->intern->cin }}</td>
                            <td>{{ $absence->date_absence?->format('d/m/Y') }}</td>
                            <td>{{ $absence->reason }}</td>
                            <td>@statusBadge($absence->justified ? 'valide' : 'en_attente')</td>
                            <td>{{ $absence->recordedBy?->full_name }}</td>
                            @unless($isHr)
                                <td class="text-end">
                                    <a href="{{ route('absences.edit', $absence) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                                    <form action="{{ route('absences.destroy', $absence) }}" method="POST" class="d-inline" data-confirm="Cette absence sera supprimée." data-confirm-title="Supprimer l'absence ?" data-confirm-ok="Supprimer">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Supprimer</button>
                                    </form>
                                </td>
                            @endunless
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isHr ? 5 : 6 }}" class="text-center text-muted">Aucune absence enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $absences->links() }}
    </div>
</div>
@endsection
