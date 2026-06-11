@extends('layouts.app')

@section('title', 'Archives RH')

@section('content')
<x-ui.page-header title="Archives des attestations" kicker="Archives" kicker-icon="bi-archive-fill"
                  subtitle="Attestations clôturées et archivées." />

<div class="card card-soft fade-in">
    <div class="card-body">
        {{-- ── Recherche ────────────────────────────────────────────── --}}
        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (stagiaire, CIN)" />

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Stagiaire</th>
                        <th>Date génération</th>
                        <th>Traitée par</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attestations as $requestItem)
                        <tr>
                            <td>{{ $requestItem->intern->user?->full_name ?? $requestItem->intern->cin }}</td>
                            <td>{{ $requestItem->rh_processed_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ $requestItem->rhProcessor?->full_name ?? '-' }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex justify-content-end gap-1 flex-nowrap">
                                    <a href="{{ route('attestations.show', $requestItem->intern) }}" class="btn btn-sm btn-outline-primary btn-icon" title="Voir l'attestation" aria-label="Voir l'attestation"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('rh.archives.download', $requestItem) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="Télécharger le PDF" aria-label="Télécharger le PDF"><i class="bi bi-download"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Aucune attestation archivée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $attestations->links() }}
    </div>
</div>
@endsection
