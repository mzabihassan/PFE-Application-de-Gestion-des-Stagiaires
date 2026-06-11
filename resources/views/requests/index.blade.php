@extends('layouts.app')

@section('title', 'Demandes')

@section('content')
@php
    use App\Support\AttestationWorkflow;

    $user = auth()->user();
    $isAdmin = $user->hasRole('Administrateur');
    $canProcess = $user->hasRole('Administrateur', 'Responsable de competence');
    $canSupervisorValidate = ! $isAdmin && $user->hasRole('Encadrant');
    $canRcValidate = ! $isAdmin && $user->hasRole('Responsable de competence');
    $canRhComplete = $user->hasRole('Administrateur', 'Responsable RH');
    $canCreate = $user->hasRole('Stagiaire') && $user->intern !== null;

    $printableAttestationStatuses = AttestationWorkflow::printableStatuses();
    $adminGeneratableAttestationStatuses = ['attente_validation_encadrant', 'attente_validation_rc', 'transmise_rh'];

    // Which type filters to show.
    $typeFilters = [
        '' => 'Tous les types',
        'attestation' => 'Attestation',
        'absence' => 'Absence',
        'prolongation' => 'Prolongation',
        'retard_attestation' => 'Retard attestation',
        'autre' => 'Autre',
    ];
@endphp

<x-ui.page-header title="Demandes" kicker="File de traitement" kicker-icon="bi-inbox-fill"
                  subtitle="Les demandes à traiter apparaissent en premier.">
    @if($canCreate)
        <x-slot:actions>
            <a href="{{ route('requests.create') }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> Nouvelle demande</a>
        </x-slot:actions>
    @endif
</x-ui.page-header>

<div class="card card-soft fade-in">
    <div class="card-body">
        {{-- ── Recherche & filtres ──────────────────────────────────── --}}
        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (stagiaire, CIN, message)">
            <select name="status" class="toolbar-select" data-autosubmit aria-label="Filtrer par état">
                <option value="">Toutes les demandes</option>
                <option value="pending" @selected($statusFilter === 'pending')>À traiter</option>
                <option value="done" @selected($statusFilter === 'done')>Traitées</option>
            </select>
            <select name="type" class="toolbar-select" data-autosubmit aria-label="Filtrer par type">
                @foreach($typeFilters as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value || ($value === '' && ! $type))>{{ $label }}</option>
                @endforeach
            </select>
        </x-ui.table-toolbar>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Stagiaire</th>
                        <th>Type</th>
                        <th>Étape</th>
                        <th>Traitée par</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $requestItem)
                        @php
                            $pendingRole = $requestItem->type === 'attestation'
                                ? AttestationWorkflow::pendingRole($requestItem)
                                : ($requestItem->status === 'en_attente' ? 'Responsable de competence' : null);
                            $needsMyAction =
                                ($canSupervisorValidate && $requestItem->workflow_status === 'attente_validation_encadrant')
                                || ($canRcValidate && $requestItem->workflow_status === 'attente_validation_rc')
                                || ($canRhComplete && in_array($requestItem->workflow_status, $adminGeneratableAttestationStatuses, true))
                                || ($canProcess && $requestItem->type !== 'attestation' && $requestItem->status === 'en_attente');
                        @endphp
                        <tr @class(['row-actionable' => $needsMyAction])>
                            <td>
                                <div class="fw-semibold">{{ $requestItem->intern->user?->full_name ?? $requestItem->intern->cin }}</div>
                                @if($requestItem->type === 'absence' && $requestItem->motif_absence)
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($requestItem->motif_absence, 50) }}</div>
                                @else
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($requestItem->message, 50) }}</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $requestItem->type === 'retard_attestation' ? 'Retard attestation' : ucfirst($requestItem->type) }}</div>
                                @if($requestItem->type === 'absence' && $requestItem->absence_generated_at !== null)
                                    <span class="badge text-bg-success">Absence créée</span>
                                @endif
                                @if($requestItem->type === 'attestation' && $requestItem->report_path)
                                    <a href="{{ route('requests.report', $requestItem) }}" class="small"><i class="bi bi-filetype-pdf"></i> Rapport</a>
                                @endif
                            </td>
                            <td style="min-width:200px">
                                @if($requestItem->type === 'attestation' && $requestItem->workflow_status)
                                    <span class="badge text-bg-primary mb-1">{{ AttestationWorkflow::shortLabel($requestItem->workflow_status) }}</span>
                                    @include('partials.attestation-timeline', ['requestItem' => $requestItem])
                                @else
                                    @statusBadge($requestItem->status)
                                @endif
                            </td>
                            <td class="small text-muted">{{ $requestItem->processedBy?->full_name ?? '—' }}</td>
                            <td>
                                <div class="d-flex justify-content-end gap-2 flex-nowrap">
                                    @if($requestItem->type === 'attestation')
                                        @if($canSupervisorValidate && $requestItem->workflow_status === 'attente_validation_encadrant')
                                            <form action="{{ route('requests.supervisor-validate', $requestItem) }}" method="POST" class="d-inline-flex gap-1 align-items-center">
                                                @csrf @method('PATCH')
                                                <input type="number" name="supervisor_grade" class="form-control form-control-sm" min="0" max="20" placeholder="/20" required style="width:70px;">
                                                <button type="submit" class="btn btn-sm btn-success text-nowrap">Noter &amp; valider</button>
                                            </form>
                                        @elseif($canRcValidate && $requestItem->workflow_status === 'attente_validation_rc')
                                            <form action="{{ route('requests.rc-validate', $requestItem) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-success text-nowrap">Valider rapport</button>
                                            </form>
                                        @endif

                                        @if($canRhComplete && in_array($requestItem->workflow_status, $adminGeneratableAttestationStatuses, true))
                                            <a href="{{ route('attestations.show', $requestItem->intern) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Voir</a>
                                            <form action="{{ route('requests.rh-complete', $requestItem) }}" method="POST" class="d-inline-flex gap-1 align-items-center">
                                                @csrf @method('PATCH')
                                                @if($requestItem->supervisor_grade === null)
                                                    <input type="number" name="supervisor_grade" class="form-control form-control-sm" min="0" max="20" placeholder="/20" required style="width:70px;">
                                                @endif
                                                <button type="submit" class="btn btn-sm btn-success text-nowrap">Générer</button>
                                            </form>
                                        @elseif($canRhComplete && in_array($requestItem->workflow_status, $printableAttestationStatuses, true))
                                            <a href="{{ route('attestations.show', $requestItem->intern) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Voir / Imprimer</a>
                                            @if(in_array($requestItem->workflow_status, ['attestation_generee', 'attestation_prete'], true))
                                                <form action="{{ route('requests.rh-printed', $requestItem) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Marquer imprimée</button>
                                                </form>
                                            @endif
                                        @endif
                                    @elseif($canProcess && $requestItem->status === 'en_attente')
                                        <form action="{{ route('requests.process', $requestItem) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="acceptee">
                                            <button type="submit" class="btn btn-sm btn-success text-nowrap">Accepter</button>
                                        </form>
                                        <form action="{{ route('requests.process', $requestItem) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="refusee">
                                            <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">Refuser</button>
                                        </form>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            <x-ui.empty-state icon="bi-inbox" title="Aucune demande">
                                Aucune demande ne correspond à ce filtre.
                            </x-ui.empty-state>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
