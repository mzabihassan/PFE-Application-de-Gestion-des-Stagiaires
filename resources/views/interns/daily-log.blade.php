@extends('layouts.app')

@section('title', 'Mon Journal de Stage')

@section('content')
@php
    use Carbon\Carbon;

    $today        = now()->toDateString();
    $prevWeek     = $weekStart->copy()->subWeek()->toDateString();
    $nextWeek     = $weekStart->copy()->addWeek()->toDateString();
    $isCurrentWk  = $weekStart->isSameWeek(now());

    // Build the 5 weekdays (Mon–Fri) to show
    $days = [];
    for ($i = 0; $i < 5; $i++) {
        $date      = $weekStart->copy()->addDays($i);
        $dateStr   = $date->format('Y-m-d');
        $log       = $logs->get($dateStr);
        $isPast    = $date->lte(now()->endOfDay());
        $isToday   = $dateStr === $today;
        $isFuture  = !$isPast;
        $days[]    = compact('date', 'dateStr', 'log', 'isToday', 'isFuture');
    }

    $frDays = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi'];
@endphp

{{-- ── Page header ───────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap fade-in">
    <div>
        <div class="page-kicker"><i class="bi bi-journal-text"></i> Journal de stage</div>
        <h1 class="h3 mb-0">Mon journal</h1>
        <p class="text-muted small mb-0 mt-1">
            Documentez votre présence et vos activités quotidiennes.
            Ces données alimentent le rapport hebdomadaire IA de votre encadrant.
        </p>
    </div>

    {{-- Week navigation --}}
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('daily-log.index', ['week' => $prevWeek]) }}"
           class="btn btn-outline-secondary btn-sm" title="Semaine précédente"><i class="bi bi-chevron-left"></i></a>

        <span class="fw-semibold px-2 text-nowrap">
            {{ $weekStart->isoFormat('D MMM') }} – {{ $weekStart->copy()->addDays(4)->isoFormat('D MMM YYYY') }}
            @if($isCurrentWk) <span class="badge text-bg-primary ms-1 fw-normal" style="font-size:.7rem">Cette semaine</span> @endif
        </span>

        <a href="{{ route('daily-log.index', ['week' => $nextWeek]) }}"
           class="btn btn-outline-secondary btn-sm"
           @if($isCurrentWk) style="pointer-events:none;opacity:.4" @endif
           title="Semaine suivante"><i class="bi bi-chevron-right"></i></a>
    </div>
</div>

{{-- ── Daily cards grid ──────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    @foreach($days as $i => $day)
    @php
        $isPresent = $day['log'] ? $day['log']->is_present : true;
        $note      = $day['log']?->daily_note ?? '';
        $hasLog    = $day['log'] !== null;
        $cardClass = $day['isToday'] ? 'border-primary shadow-sm' : '';
    @endphp
    <div class="col-12 col-md-6 col-xl">
        <div class="card card-soft h-100 daily-card {{ $cardClass }}"
             data-date="{{ $day['dateStr'] }}">

            {{-- Card header --}}
            <div class="card-header d-flex justify-content-between align-items-center py-2"
                 style="background:transparent;border-bottom:1px solid rgba(0,0,0,.06)">
                <div>
                    <div class="fw-bold small">{{ $frDays[$i] }}</div>
                    <div class="text-muted" style="font-size:.78rem">
                        {{ $day['date']->isoFormat('D MMM') }}
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                    @if($day['isToday'])
                        <span class="badge text-bg-primary text-nowrap" style="font-size:.65rem">Aujourd'hui</span>
                    @endif
                    @if($hasLog)
                        <span class="badge text-bg-success journalised-badge text-nowrap" style="font-size:.65rem">
                            ✓ Journalisé ({{ $isPresent ? 'Présent' : 'Absent' }})
                        </span>
                    @endif
                </div>
            </div>

            <div class="card-body d-flex flex-column gap-3">

                @if($day['isFuture'])
                    {{-- Future day placeholder --}}
                    <div class="text-center py-3" style="font-size:.85rem">
                        <div class="text-muted mb-2">
                            <span style="font-size:1.5rem;opacity:.3">🔒</span><br>
                            Pas encore disponible
                        </div>
                        <button type="button" 
                                class="btn btn-outline-primary btn-sm request-absence-btn" 
                                data-bs-toggle="modal" 
                                data-bs-target="#absenceRequestModal"
                                data-date="{{ $day['date']->isoFormat('dddd D MMMM YYYY') }}"
                                data-date-str="{{ $day['date']->format('d/m/Y') }}">
                            <i class="bi bi-calendar-event me-1"></i> Demander une absence
                        </button>
                    </div>
                @else
                    {{-- Presence toggle --}}
                    <div>
                        <label class="form-label small fw-semibold mb-2">Présence</label>
                        <div class="presence-toggle d-flex gap-2"
                             data-date="{{ $day['dateStr'] }}"
                             data-present="{{ $isPresent ? '1' : '0' }}">
                            <button type="button"
                                    class="btn btn-sm w-50 presence-btn {{ $isPresent ? 'btn-success active' : 'btn-outline-success' }}"
                                    data-value="1"
                                    @if($hasLog) disabled @endif>
                                ✓ Présent
                            </button>
                            <button type="button"
                                    class="btn btn-sm w-50 presence-btn {{ !$isPresent ? 'btn-danger active' : 'btn-outline-danger' }}"
                                    data-value="0"
                                    @if($hasLog) disabled @endif>
                                ✗ Absent
                            </button>
                        </div>
                    </div>

                    {{-- Daily note --}}
                    <div class="flex-grow-1">
                        <label class="form-label small fw-semibold mb-1">
                            Qu'avez-vous fait aujourd'hui ?
                        </label>
                        <textarea
                            class="form-control daily-note-input"
                            rows="5"
                            data-date="{{ $day['dateStr'] }}"
                            placeholder="Décrivez vos activités, avancées, difficultés rencontrées…"
                            style="resize:none;font-size:.83rem"
                        >{{ $note }}</textarea>
                        <div class="daily-save-status text-success small mt-1"
                             data-date="{{ $day['dateStr'] }}"
                             style="display:none;min-height:1.2em">
                            ✓ Sauvegardé
                        </div>
                    </div>
                @endif

            </div>{{-- /.card-body --}}
        </div>
    </div>
    @endforeach
</div>

{{-- ── Tasks in Progress Section ────────────────────────────── --}}
<div class="card card-soft mb-4 fade-in">
    <div class="card-body">
        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
            📋 Mes tâches en cours
        </h5>
        
        @if($inProgressTasks->isEmpty())
            <div class="text-center text-muted py-3" style="font-size:.9rem">
                Aucune tâche en cours pour le moment.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Stage</th>
                            <th>Assignée par</th>
                            <th>Date limite</th>
                            <th>Statut</th>
                            <th>Commentaire hebdomadaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inProgressTasks as $task)
                            <tr>
                                <td class="fw-semibold">{{ $task->title }}</td>
                                <td>{{ $task->internship?->title ?? '-' }}</td>
                                <td>{{ $task->assignedBy?->full_name ?? '-' }}</td>
                                <td>
                                    @if($task->due_date)
                                        <span class="text-nowrap">
                                            {{ $task->due_date->format('d/m/Y') }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <select class="form-select form-select-sm task-status" data-url="{{ route('tasks.status', $task) }}" style="width: auto; min-width: 120px;">
                                        @foreach(['a_faire' => 'À faire', 'en_cours' => 'En cours', 'termine' => 'Terminé'] as $key => $label)
                                            <option value="{{ $key }}" @selected($task->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="min-width: 250px;">
                                    <div class="input-group input-group-sm">
                                        <textarea
                                            class="form-control weekly-comment-input"
                                            rows="2"
                                            placeholder="Votre avancement cette semaine…"
                                            data-url="{{ route('tasks.weekly-comment', $task) }}"
                                            data-task-id="{{ $task->id }}"
                                            style="resize:none; font-size:0.8rem"
                                        >{{ $task->weekly_comment }}</textarea>
                                    </div>
                                    <div class="weekly-comment-status text-success small mt-1" data-task-id="{{ $task->id }}" style="display:none">✓ Sauvegardé</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- ── Info card ─────────────────────────────────────────────── --}}
<div class="card card-soft">
    <div class="card-body d-flex gap-3 align-items-start">
        <span style="font-size:2rem">💡</span>
        <div>
            <h6 class="fw-semibold mb-1">Comment utiliser ce journal ?</h6>
            <ul class="small text-muted mb-0 ps-3">
                <li>Cochez <strong>Présent</strong> ou <strong>Absent</strong> pour chaque jour travaillé.</li>
                <li>Rédigez une note décrivant ce que vous avez accompli, vos blocages, ou vos questions.</li>
                <li>Vos notes s'enregistrent automatiquement après chaque modification.</li>
                <li>Votre encadrant génère chaque semaine un rapport IA basé sur vos saisies et vos tâches.</li>
            </ul>
        </div>
    </div>
</div>

{{-- Modal Demande d'absence --}}
<div class="modal fade" id="absenceRequestModal" tabindex="-1" aria-labelledby="absenceRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('requests.store') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="type" value="absence">
            
            <div class="modal-header">
                <h5 class="modal-title" id="absenceRequestModalLabel">Demande d'absence</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body vstack gap-3">
                <div class="alert alert-info py-2 px-3 mb-0" style="font-size: 0.88rem;">
                    Vous demandez une absence pour le <strong id="absenceModalTargetDate"></strong>.
                </div>
                
                <div>
                    <label for="modal_motif_absence" class="form-label small fw-semibold">Motif d'absence</label>
                    <input type="text" 
                           class="form-control" 
                           id="modal_motif_absence" 
                           name="motif_absence" 
                           required 
                           placeholder="Exemple : Maladie, rendez-vous médical, urgence...">
                </div>
                
                <div>
                    <label for="modal_message" class="form-label small fw-semibold">Message / Justification</label>
                    <textarea class="form-control" 
                              id="modal_message" 
                              name="message" 
                              rows="4" 
                              required></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-sm btn-success">Envoyer la demande</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const CSRF  = $('meta[name="csrf-token"]').attr('content');
    const URL   = '{{ route('daily-log.store') }}';
    let timers  = {};

    // ── Shared save function ───────────────────────────────────
    function saveLog(date) {
        const $card     = $(`.daily-card[data-date="${date}"]`);
        const $toggle   = $card.find(`.presence-toggle[data-date="${date}"]`);
        const $note     = $card.find(`.daily-note-input[data-date="${date}"]`);
        const $status   = $card.find(`.daily-save-status[data-date="${date}"]`);
        const isPresent = parseInt($toggle.data('present'), 10);

        $status.hide();

        $.ajax({
            url: URL,
            method: 'POST',
            data: {
                _token:     CSRF,
                log_date:   date,
                is_present: isPresent,
                daily_note: $note.val(),
            },
        })
        .done(() => {
            $status.fadeIn().delay(2500).fadeOut();
            
            // Disable the presence buttons to lock them
            $card.find('.presence-btn').prop('disabled', true);
            
            // Update badge to show "✓ Journalisé (Présent)" or "✓ Journalisé (Absent)"
            const statusLabel = isPresent === 1 ? 'Présent' : 'Absent';
            let $badge = $card.find('.journalised-badge');
            if ($badge.length) {
                $badge.text(`✓ Journalisé (${statusLabel})`);
            } else {
                const $container = $card.find('.card-header .d-flex.flex-column');
                $container.append(`<span class="badge text-bg-success journalised-badge text-nowrap" style="font-size:.65rem">✓ Journalisé (${statusLabel})</span>`);
            }
        })
        .fail((xhr) => {
            const msg = xhr.responseJSON?.message || 'Erreur de sauvegarde.';
            $status.text('⚠ ' + msg).addClass('text-danger').fadeIn();
        });
    }

    // ── Presence toggle ────────────────────────────────────────
    $(document).on('click', '.presence-btn', function () {
        const $btn    = $(this);
        const $toggle = $btn.closest('.presence-toggle');
        const date    = $toggle.data('date');
        const val     = parseInt($btn.data('value'), 10);

        // Don't re-save if already selected
        if ($btn.hasClass('active')) return;

        // Update UI
        $toggle.find('.presence-btn').each(function () {
            const v = parseInt($(this).data('value'), 10);
            if (v === 1) {
                $(this).toggleClass('btn-success active', val === 1)
                       .toggleClass('btn-outline-success', val !== 1);
            } else {
                $(this).toggleClass('btn-danger active', val === 0)
                       .toggleClass('btn-outline-danger', val !== 0);
            }
        });
        $toggle.data('present', val);

        saveLog(date);
    });

    // ── Note auto-save (debounced 800ms) ──────────────────────
    $(document).on('input', '.daily-note-input', function () {
        const date = $(this).data('date');
        clearTimeout(timers[date]);
        timers[date] = setTimeout(() => saveLog(date), 800);
    });

    // ── Task status change ─────────────────────────────────────
    $(document).on('change', '.task-status', function () {
        const $select = $(this);

        $.ajax({
            url: $select.data('url'),
            method: 'PATCH',
            data: {
                _token: CSRF,
                status: $select.val()
            }
        }).done(function () {
            // Fade out the row if the task is no longer in_progress ("en_cours")
            if ($select.val() !== 'en_cours') {
                $select.closest('tr').fadeOut(400, function () {
                    $(this).remove();
                    if ($('.table-responsive tbody tr').length === 0) {
                        $('.table-responsive').replaceWith('<div class="text-center text-muted py-3" style="font-size:.9rem">Aucune tâche en cours pour le moment.</div>');
                    }
                });
            }
        }).fail(function (xhr) {
            const message = xhr.status === 403
                ? 'Vous n\'êtes pas autorisé à modifier cette tâche.'
                : 'Erreur lors de la mise à jour du statut.';

            toast(message, 'error');
        });
    });

    // ── Weekly comment auto-save (debounced 900ms) ──────────────
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
                    _token: CSRF,
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

    // ── Absence request modal pre-fill ─────────────────────────
    $(document).on('click', '.request-absence-btn', function () {
        const dateFormatted = $(this).data('date');
        const dateStr = $(this).data('date-str');
        
        $('#absenceModalTargetDate').text(dateFormatted);
        $('#modal_message').val(`Demande d'absence pour le ${dateStr}.\n\nDétails : `);
        $('#modal_motif_absence').val('');
    });
});
</script>

<style>
    .daily-card {
        transition: box-shadow .2s, border-color .2s;
    }
    .daily-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,.1) !important;
    }
    .daily-card.border-primary {
        border-width: 2px !important;
    }
    .presence-btn {
        transition: all .15s;
    }
</style>
@endpush
