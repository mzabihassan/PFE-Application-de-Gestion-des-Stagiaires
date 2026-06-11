@extends('layouts.app')

@section('title', 'Mes stages')

@section('content')
<div class="card card-soft fade-in">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h4 mb-1">Mes stages</h1>
                <p class="text-muted mb-0">Stages qui vous sont affectés, avec leurs stagiaires et périodes.</p>
            </div>
        </div>

        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (titre, département, stagiaire)">
            <select name="status" class="toolbar-select" data-autosubmit aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                <option value="planifie" @selected($status === 'planifie')>Planifié</option>
                <option value="en_cours" @selected($status === 'en_cours')>En cours</option>
                <option value="termine" @selected($status === 'termine')>Terminé</option>
            </select>
        </x-ui.table-toolbar>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Titre du stage</th>
                        <th>Département</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th>Stagiaires</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($internships as $internship)
                        <tr>
                            <td>
                                <div>{{ $internship->title }}</div>
                                @if($internship->description)
                                    <small class="text-muted">{{ $internship->description }}</small>
                                @endif
                            </td>
                            <td>{{ $internship->department }}</td>
                            <td>{{ $internship->start_date?->format('d/m/Y') ?? '-' }} - {{ $internship->end_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                @statusBadge($internship->status)
                            </td>
                            <td>
                                @forelse($internship->interns as $intern)
                                    <a
                                        href="{{ route('supervisor.interns.show', $intern) }}"
                                        class="btn btn-sm btn-outline-success me-1 mb-1"
                                    >
                                        {{ $intern->user?->full_name ?? $intern->cin }}
                                    </a>
                                @empty
                                    <div class="text-muted">Aucun stagiaire lié</div>
                                @endforelse
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                <a href="{{ route('supervisor.internships.show', $internship) }}" class="btn btn-sm btn-outline-primary">Consulter</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Aucun stage ne vous est affecté pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $internships->links() }}
    </div>
</div>
@endsection
