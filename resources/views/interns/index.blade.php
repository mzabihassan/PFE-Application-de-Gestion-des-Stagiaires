@extends('layouts.app')

@section('title', 'Stagiaires')

@section('content')
@php
    $isSupervisor = auth()->user()->hasRole('Encadrant');
    $isHr = auth()->user()->hasRole('Responsable RH');
    $canManageInterns = auth()->user()->hasRole('Administrateur', 'Responsable de competence');
    $highlightInternId = $highlightInternId ?? null;
@endphp

<x-ui.page-header title="Gestion des stagiaires" kicker="Annuaire" kicker-icon="bi-mortarboard-fill">
    @if(auth()->user()->hasRole('Responsable de competence'))
        <x-slot:actions>
            <a href="{{ route('interns.create-intern') }}" class="btn btn-success">
                <i class="bi bi-person-plus"></i> Nouveau stagiaire
            </a>
        </x-slot:actions>
    @endif
</x-ui.page-header>

<div class="card card-soft fade-in">
    <div class="card-body">
        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (nom, CIN, école, spécialité)">
            <select name="status" class="toolbar-select" data-autosubmit aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                <option value="active" @selected($status === 'active')>Actifs</option>
                <option value="completed" @selected($status === 'completed')>Terminés</option>
                <option value="no_internship" @selected($status === 'no_internship')>Sans stage</option>
                @unless($isSupervisor)
                    <option value="archived" @selected($status === 'archived')>Archivés</option>
                @endunless
            </select>
        </x-ui.table-toolbar>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>CIN</th>
                        <th>Compte</th>
                        <th>École / Spécialité</th>
                        <th>Période</th>
                        <th>État</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($interns as $intern)
                        @php
                            $isCompleted = $intern->end_date !== null && $intern->end_date->lt(today()->subDay());
                            $hasAssignedInternship = $intern->internships
                                ->contains(fn ($internship) => $internship->supervisor_id !== null);
                            $attestationRequest = $intern->requests
                                ->where('type', 'attestation')
                                ->sortByDesc('created_at')
                                ->first();
                            $hasSupervisorValidation = $attestationRequest?->supervisor_validated_at !== null;
                            $hasRcValidation = $attestationRequest?->rc_validated_at !== null;
                            $canShowAttestation = ! $isHr || ($isCompleted && $hasSupervisorValidation && $hasRcValidation);
                            $showHighlight = (int) $highlightInternId === (int) $intern->id;
                        @endphp
                        <tr @class(['table-success' => $showHighlight])>
                            <td class="font-monospace">{{ $intern->cin }}</td>
                            <td class="fw-semibold">{{ $intern->user?->full_name ?? 'Non lié' }}</td>
                            <td>
                                <div>{{ $intern->school }}</div>
                                <small class="text-muted">{{ $intern->specialty }}</small>
                            </td>
                            <td class="text-nowrap">{{ $intern->start_date?->format('d/m/Y') }} <span class="text-muted">→</span> {{ $intern->end_date?->format('d/m/Y') }}</td>
                            <td>
                                @statusBadge($intern->is_archived ? 'archive' : ($isCompleted ? 'termine' : ($hasAssignedInternship ? 'en_cours' : 'en_attente')))
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-wrap">
                                    <a href="{{ $isSupervisor ? route('supervisor.interns.show', $intern) : route('interns.show', $intern) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="Voir" aria-label="Voir"><i class="bi bi-eye"></i></a>

                                    @unless($isSupervisor)
                                        @unless($isHr)
                                            <a href="{{ route('interns.edit', $intern) }}" class="btn btn-sm btn-outline-primary btn-icon" title="Modifier" aria-label="Modifier"><i class="bi bi-pencil"></i></a>

                                            @if($intern->is_archived)
                                                <form action="{{ route('interns.restore', $intern) }}" method="POST" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm btn-outline-success btn-icon" type="submit" title="Restaurer" aria-label="Restaurer"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                            @elseif($isCompleted)
                                                <form action="{{ route('interns.archive', $intern) }}" method="POST" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm btn-outline-warning btn-icon" type="submit" title="Archiver" aria-label="Archiver"><i class="bi bi-archive"></i></button>
                                                </form>
                                            @endif
                                        @endunless

                                        @if($canShowAttestation)
                                            <a href="{{ route('attestations.show', $intern) }}" class="btn btn-sm btn-outline-info btn-icon" title="{{ $isHr ? 'Générer attestation' : 'Attestation' }}" aria-label="{{ $isHr ? 'Générer attestation' : 'Attestation' }}"><i class="bi bi-award"></i></a>
                                        @endif
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">
                            <x-ui.empty-state icon="bi-inbox" title="Aucun stagiaire">
                                Aucun résultat ne correspond à votre recherche.
                            </x-ui.empty-state>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $interns->links() }}</div>
    </div>
</div>
@endsection
