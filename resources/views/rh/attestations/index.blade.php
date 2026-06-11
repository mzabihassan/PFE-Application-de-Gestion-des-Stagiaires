@extends('layouts.app')

@section('title', 'Attestations')

@section('content')
<x-ui.page-header title="Attestations" kicker="File RH" kicker-icon="bi-award-fill"
                  subtitle="Les attestations à générer apparaissent en premier." />

<div class="card card-soft fade-in">
    <div class="card-body">
        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (stagiaire, CIN)">
            <select name="status" class="toolbar-select" data-autosubmit aria-label="Filtrer par étape">
                <option value="">Toutes les étapes</option>
                @foreach($statusFilters as $key => $filter)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $filter['label'] }}</option>
                @endforeach
            </select>
        </x-ui.table-toolbar>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Stagiaire</th>
                        <th>Stage</th>
                        <th>Workflow</th>
                        <th>État</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attestations as $requestItem)
                        @php $internship = $requestItem->intern->internships->sortByDesc('end_date')->first(); @endphp
                        <tr>
                            <td>{{ $requestItem->intern->user?->full_name ?? $requestItem->intern->cin }}</td>
                            <td>
                                <div>{{ $internship?->title ?? '-' }}</div>
                                <small class="text-muted">{{ $internship?->department ?? '-' }}</small>
                            </td>
                            <td>
                                @include('partials.attestation-timeline', ['requestItem' => $requestItem])
                            </td>
                            <td>
                                @statusBadge($requestItem->workflow_status)
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex justify-content-end align-items-center gap-2 flex-nowrap">
                                    @if($requestItem->workflow_status === 'transmise_rh')
                                        <a href="{{ route('attestations.show', $requestItem->intern) }}" class="btn btn-sm btn-primary text-nowrap">Générer attestation</a>
                                        <form action="{{ route('requests.rh-complete', $requestItem) }}" method="POST" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-success text-nowrap" type="submit">Envoyer message</button>
                                        </form>
                                    @else
                                        <a href="{{ route('interns.show', $requestItem->intern) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Voir</a>
                                    @endif

                                    @if($requestItem->workflow_status !== 'transmise_rh')
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                @if(in_array($requestItem->workflow_status, ['attestation_generee', 'attestation_prete', 'attestation_imprimee', 'attestation_recuperee'], true))
                                                    <li><a class="dropdown-item" href="{{ route('attestations.show', $requestItem->intern) }}">Imprimer</a></li>
                                                @endif
                                                @if(in_array($requestItem->workflow_status, ['attestation_generee', 'attestation_prete'], true))
                                                    <li>
                                                        <form action="{{ route('requests.rh-printed', $requestItem) }}" method="POST" class="m-0">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button class="dropdown-item" type="submit">Marquer imprimée</button>
                                                        </form>
                                                    </li>
                                                @endif
                                                @if(in_array($requestItem->workflow_status, ['attestation_generee', 'attestation_prete', 'attestation_imprimee'], true))
                                                    <li>
                                                        <form action="{{ route('requests.rh-recovered', $requestItem) }}" method="POST" class="m-0">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button class="dropdown-item" type="submit">Récupérée</button>
                                                        </form>
                                                    </li>
                                                @endif
                                                @if(in_array($requestItem->workflow_status, ['attestation_generee', 'attestation_prete', 'attestation_imprimee', 'attestation_recuperee'], true))
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="{{ route('requests.rh-archive', $requestItem) }}" method="POST" class="m-0" data-confirm="L'attestation sera déplacée vers les archives." data-confirm-title="Archiver l'attestation ?" data-confirm-ok="Archiver" data-confirm-variant="neutral">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button class="dropdown-item text-warning" type="submit">Archiver</button>
                                                        </form>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Aucune attestation à traiter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $attestations->links() }}
    </div>
</div>
@endsection
