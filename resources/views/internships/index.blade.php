@extends('layouts.app')

@section('title', 'Stages')

@section('content')
@php $isHr = auth()->user()->hasRole('Responsable RH'); @endphp

<x-ui.page-header title="Gestion des stages" kicker="Stages" kicker-icon="bi-briefcase-fill">
    <x-slot:actions>
        <a href="{{ route('internships.create') }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> Nouveau stage</a>
    </x-slot:actions>
</x-ui.page-header>

<div class="card card-soft fade-in">
    <div class="card-body">

        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (titre, département)">
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
                        <th>Titre</th>
                        <th>Stagiaire</th>
                        <th>Encadrant</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($internships as $internship)
                        <tr>
                            <td>
                                <div>{{ $internship->title }}</div>
                                <small class="text-muted">{{ $internship->department }}</small>
                            </td>
                            <td>
                                @if($internship->interns->isEmpty())
                                    -
                                @else
                                    {{ $internship->interns->map(fn ($intern) => $intern->user?->full_name ?? $intern->cin)->join(', ') }}
                                @endif
                            </td>
                            <td>{{ $internship->supervisor?->full_name ?? 'Non assigné' }}</td>
                            <td>{{ $internship->start_date?->format('d/m/Y') }} - {{ $internship->end_date?->format('d/m/Y') }}</td>
                            <td>
                                <select class="form-select form-select-sm internship-status" data-url="{{ route('internships.status', $internship) }}">
                                    @foreach(['planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé'] as $key => $label)
                                        <option value="{{ $key }}" @selected($internship->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <a href="{{ route('internships.edit', $internship) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                                    <a href="{{ route('internships.convention', $internship) }}" class="btn btn-sm btn-outline-info text-nowrap">Convention</a>
                                    @unless($isHr)
                                        <form action="{{ route('internships.destroy', $internship) }}" method="POST" class="m-0" data-confirm="Ce stage sera définitivement supprimé." data-confirm-title="Supprimer le stage ?" data-confirm-ok="Supprimer">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger text-nowrap" type="submit">Supprimer</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Aucun stage.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $internships->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('.internship-status').on('change', function () {
            const $select = $(this);

            $.ajax({
                url: $select.data('url'),
                method: 'PATCH',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    status: $select.val()
                }
            }).fail(function () {
                toast('Erreur lors de la mise à jour du statut.', 'error');
            });
        });
    });
</script>
@endpush
