@extends('layouts.app')

@section('title', 'Tâches')

@section('content')
@php
    $canManage = auth()->user()->hasRole('Administrateur', 'Encadrant');
    $view = (string) request()->query('view', 'table');
    $isKanban = $view === 'kanban';
    $taskColumns = [
        'a_faire' => 'À faire',
        'en_cours' => 'En cours',
        'termine' => 'Terminé',
    ];
    $tasksByStatus = $tasks->getCollection()->groupBy('status');
    $isEncadrant = auth()->user()->hasRole('Encadrant');
    $isIntern = auth()->user()->hasRole('Stagiaire');
    $showAllTasks = $showAllTasks ?? false;
@endphp

<x-ui.page-header title="Gestion des tâches" kicker="Suivi" kicker-icon="bi-kanban-fill"
                  subtitle="Vue {{ $isKanban ? 'kanban' : 'liste' }} des tâches.">
    <x-slot:actions>
        <div class="btn-group" role="group" aria-label="Changer la vue">
            <a class="btn btn-outline-secondary btn-sm {{ $isKanban ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'kanban']) }}"><i class="bi bi-kanban"></i> Kanban</a>
            <a class="btn btn-outline-secondary btn-sm {{ ! $isKanban ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'table']) }}"><i class="bi bi-table"></i> Table</a>
        </div>
        @if($canManage)
            <a href="{{ route('tasks.create') }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> Nouvelle tâche</a>
        @endif
    </x-slot:actions>
</x-ui.page-header>

<div class="card card-soft fade-in">
    <div class="card-body">
        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (titre, stagiaire, stage)" :preserve="['view' => $view]">
            @if($isEncadrant)
                <select name="internship_id" class="toolbar-select" data-autosubmit aria-label="Filtrer par stage">
                    <option value="">Tous les stages</option>
                    @foreach($internships ?? [] as $internship)
                        <option value="{{ $internship->id }}" @selected((string) $internshipId === (string) $internship->id)>{{ $internship->title }}</option>
                    @endforeach
                </select>
            @else
                <select name="status" class="toolbar-select" data-autosubmit aria-label="Filtrer par statut">
                    <option value="">Tous les statuts</option>
                    <option value="a_faire" @selected($status === 'a_faire')>À faire</option>
                    <option value="en_cours" @selected($status === 'en_cours')>En cours</option>
                    <option value="termine" @selected($status === 'termine')>Terminé</option>
                </select>
            @endif
            <select name="due" class="toolbar-select" data-autosubmit aria-label="Filtrer par échéance">
                <option value="">Toutes les échéances</option>
                <option value="overdue" @selected($due === 'overdue')>En retard</option>
                <option value="upcoming" @selected($due === 'upcoming')>À venir</option>
            </select>
            @if($isIntern)
                <label class="toolbar-check">
                    <input type="checkbox" name="show_all" value="1" @checked($showAllTasks) data-autosubmit class="form-check-input">
                    <span>Toutes les tâches du stage</span>
                </label>
            @endif
        </x-ui.table-toolbar>

        @if($isKanban)
            <div class="kanban-board">
                <div class="row g-3">
                    @foreach($taskColumns as $key => $label)
                        @php $columnTasks = $tasksByStatus->get($key, collect()); @endphp
                        <div class="col-12 col-lg-4">
                            <div class="kanban-column" data-status="{{ $key }}">
                                <div class="kanban-header">
                                    <div class="fw-semibold">{{ $label }}</div>
                                    <span class="badge text-bg-secondary">{{ $columnTasks->count() }}</span>
                                </div>
                                <div class="kanban-body">
                                    @forelse($columnTasks as $task)
                                        @php $isOwner = (string) $task->assigned_to === (string) auth()->id(); @endphp
                                        <div
                                            class="card kanban-card"
                                            data-task-id="{{ $task->id }}"
                                            data-owner-id="{{ $task->assigned_to }}"
                                            data-status="{{ $task->status }}"
                                            data-update-url="{{ route('tasks.status', $task) }}"
                                            @if($isIntern && $isOwner) draggable="true" @endif
                                        >
                                            <div class="card-body">
                                                <div class="kanban-card-title">{{ $task->title }}</div>
                                                <div class="mt-1">
                                                    <span class="badge text-bg-light kanban-stage-chip" title="{{ $task->internship?->title }}">{{ $task->internship?->title ?? '-' }}</span>
                                                </div>
                                                <div class="small text-muted mt-2">Assignée à : {{ $task->assignedTo?->full_name ?? '-' }}</div>
                                                <div class="small text-muted">Date limite : {{ $task->due_date?->format('d/m/Y') ?? '-' }}</div>
                                                @if($isIntern && $isOwner)
                                                    <div class="mt-2">
                                                        <textarea
                                                            class="form-control form-control-sm weekly-comment-input"
                                                            rows="2"
                                                            placeholder="Commentaire hebdomadaire…"
                                                            data-url="{{ route('tasks.weekly-comment', $task) }}"
                                                            data-task-id="{{ $task->id }}"
                                                            style="resize:none;font-size:0.78rem"
                                                        >{{ $task->weekly_comment }}</textarea>
                                                        <div class="weekly-comment-status text-success small mt-1" data-task-id="{{ $task->id }}" style="display:none">✓ Sauvegardé</div>
                                                    </div>
                                                @endif
                                                @if(! $isIntern)
                                                    <div class="mt-2">
                                                        <select class="form-select form-select-sm task-status" data-url="{{ route('tasks.status', $task) }}">
                                                            @foreach($taskColumns as $statusKey => $statusLabel)
                                                                <option value="{{ $statusKey }}" @selected($task->status === $statusKey)>{{ $statusLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endif
                                                @if($canManage)
                                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                                        <a href="{{ route('tasks.edit', $task) }}" class="btn btn-sm btn-outline-primary btn-icon" title="Modifier" aria-label="Modifier"><i class="bi bi-pencil"></i></a>
                                                        <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="m-0" data-confirm="Cette tâche sera définitivement supprimée." data-confirm-title="Supprimer la tâche ?" data-confirm-ok="Supprimer">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-sm btn-outline-danger btn-icon" type="submit" title="Supprimer" aria-label="Supprimer"><i class="bi bi-trash3"></i></button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-muted small">Aucune tâche.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Stage</th>
                            <th>Assignée par</th>
                            <th>Assignée à</th>
                            <th>Date limite</th>
                            <th>Statut</th>
                            @if($isIntern)
                                <th>Commentaire hebdomadaire</th>
                            @endif
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tasks as $task)
                            @php $isOwner = (string) $task->assigned_to === (string) auth()->id(); @endphp
                            <tr>
                                <td class="fw-semibold">{{ $task->title }}</td>
                                <td>{{ $task->internship?->title ?? '-' }}</td>
                                <td>{{ $task->assignedBy?->full_name }}</td>
                                <td>{{ $task->assignedTo?->full_name }}</td>
                                <td class="text-nowrap">{{ $task->due_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>
                                    <select class="form-select form-select-sm task-status" data-url="{{ route('tasks.status', $task) }}">
                                        @foreach(['a_faire' => 'À faire', 'en_cours' => 'En cours', 'termine' => 'Terminé'] as $key => $label)
                                            <option value="{{ $key }}" @selected($task->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                @if($isIntern)
                                    <td style="min-width:220px">
                                        @if($isOwner)
                                            <div class="input-group input-group-sm">
                                                <textarea
                                                    class="form-control weekly-comment-input"
                                                    rows="2"
                                                    placeholder="Votre avancement cette semaine…"
                                                    data-url="{{ route('tasks.weekly-comment', $task) }}"
                                                    data-task-id="{{ $task->id }}"
                                                    style="resize:none;font-size:0.8rem"
                                                >{{ $task->weekly_comment }}</textarea>
                                            </div>
                                            <div class="weekly-comment-status text-success small mt-1" data-task-id="{{ $task->id }}" style="display:none">✓ Sauvegardé</div>
                                        @else
                                            <span class="text-muted small">{{ $task->weekly_comment ?: '—' }}</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-end">
                                    @if($canManage)
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                            <a href="{{ route('tasks.edit', $task) }}" class="btn btn-sm btn-outline-primary btn-icon" title="Modifier" aria-label="Modifier"><i class="bi bi-pencil"></i></a>
                                            <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="m-0" data-confirm="Cette tâche sera définitivement supprimée." data-confirm-title="Supprimer la tâche ?" data-confirm-ok="Supprimer">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger btn-icon" type="submit" title="Supprimer" aria-label="Supprimer"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isIntern ? 8 : 7 }}" class="text-center text-muted">Aucune tâche.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-3">{{ $tasks->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('.task-status').on('change', function () {
            const $select = $(this);

            $.ajax({
                url: $select.data('url'),
                method: 'PATCH',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    status: $select.val()
                }
            }).fail(function (xhr) {
                const message = xhr.status === 403
                    ? 'Vous n\'êtes pas autorisé à modifier cette tâche.'
                    : 'Erreur lors de la mise à jour du statut.';

                toast(message, 'error');
            });
        });

        // ── Weekly comment auto-save (debounced, intern only) ──
        let commentTimers = {};
        $(document).on('input', '.weekly-comment-input', function () {
            const $ta     = $(this);
            const taskId  = $ta.data('task-id');
            const url     = $ta.data('url');
            const $status = $(`.weekly-comment-status[data-task-id="${taskId}"]`);

            clearTimeout(commentTimers[taskId]);
            $status.hide();

            commentTimers[taskId] = setTimeout(function () {
                $.ajax({
                    url: url,
                    method: 'PATCH',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        weekly_comment: $ta.val()
                    }
                }).done(function () {
                    $status.fadeIn().delay(2000).fadeOut();
                }).fail(function (xhr) {
                    const msg = xhr.status === 403
                        ? 'Non autorisé.'
                        : 'Erreur de sauvegarde.';
                    toast(msg, 'error');
                });
            }, 900);
        });

        const isIntern = {{ $isIntern ? 'true' : 'false' }};
        const userId = '{{ auth()->id() }}';

        if (isIntern) {
            $('.kanban-card[draggable="true"]').on('dragstart', function (event) {
                const $card = $(this);
                event.originalEvent.dataTransfer.setData('text/task-id', $card.data('task-id'));
            });

            $('.kanban-column').on('dragover', function (event) {
                event.preventDefault();
                $(this).addClass('kanban-drop-target');
            });

            $('.kanban-column').on('dragleave', function () {
                $(this).removeClass('kanban-drop-target');
            });

            $('.kanban-column').on('drop', function (event) {
                event.preventDefault();
                const $column = $(this);
                const taskId = event.originalEvent.dataTransfer.getData('text/task-id');
                const targetStatus = $column.data('status');
                const $card = $('.kanban-card[data-task-id="' + taskId + '"]');

                $column.removeClass('kanban-drop-target');

                if (! taskId || ! targetStatus || $card.length === 0) {
                    return;
                }

                if (String($card.data('owner-id')) !== String(userId)) {
                    toast('Vous ne pouvez pas modifier cette tâche.', 'error');
                    return;
                }

                $.ajax({
                    url: $card.data('update-url'),
                    method: 'PATCH',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        status: targetStatus
                    }
                }).done(function () {
                    $card.attr('data-status', targetStatus);
                    $card.appendTo($column.find('.kanban-body'));
                }).fail(function (xhr) {
                    const message = xhr.status === 403
                        ? 'Vous n\'êtes pas autorisé à modifier cette tâche.'
                        : 'Erreur lors de la mise à jour du statut.';

                    toast(message, 'error');
                });
            });
        }
    });
</script>
@endpush
