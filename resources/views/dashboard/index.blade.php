@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<x-ui.page-header title="Tableau de bord" :kicker="$roleName" kicker-icon="bi-grid-1x2-fill"
                  subtitle="Ce qui demande votre attention aujourd'hui." />

@if(!empty($statCards))
    <div class="row g-3 mb-4">
        @foreach($statCards as $card)
            @php
                $statIcon = match (true) {
                    str_contains(strtolower($card['label']), 'stagiaire') => 'bi-mortarboard',
                    str_contains(strtolower($card['label']), 'stage') => 'bi-briefcase',
                    str_contains(strtolower($card['label']), 'tâche') || str_contains(strtolower($card['label']), 'tache') => 'bi-list-check',
                    str_contains(strtolower($card['label']), 'demande') => 'bi-file-earmark-text',
                    str_contains(strtolower($card['label']), 'attestation') => 'bi-award',
                    str_contains(strtolower($card['label']), 'absence') => 'bi-calendar-x',
                    str_contains(strtolower($card['label']), 'message') => 'bi-chat-dots',
                    default => 'bi-bar-chart-line',
                };
            @endphp
            <div class="col-6 col-lg-3 fade-in">
                <x-ui.stat-card :icon="$statIcon" :label="$card['label']" :value="$card['value']" />
            </div>
        @endforeach
    </div>
@endif

{{-- ── Action panels: what needs attention now ─────────────────────────── --}}
@if(!empty($attention))
    <div class="d-flex align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0">À traiter</h2>
        <span class="text-muted small">Priorisé pour votre rôle</span>
    </div>
    <div class="row g-3 mb-4">
        @foreach($attention as $panel)
            <div class="col-12 col-md-6 col-xl-4 fade-in">
                <x-ui.panel :title="$panel['title']" :icon="$panel['icon']" :tone="$panel['tone']"
                            :count="$panel['count']" :view-url="$panel['viewUrl']" :view-label="$panel['viewLabel']">
                    @forelse($panel['items'] as $item)
                        <a class="panel-item" href="{{ $item['url'] }}">
                            <div class="panel-item-main">
                                <div class="panel-item-title">{{ $item['title'] }}</div>
                                <div class="panel-item-meta">{{ $item['meta'] }}</div>
                            </div>
                            @if(!empty($item['badge']))
                                <span class="badge {{ $item['badge']['class'] }}">{{ $item['badge']['label'] }}</span>
                            @endif
                            <i class="bi bi-chevron-right panel-item-chev"></i>
                        </a>
                    @empty
                        <div class="panel-empty">
                            <i class="bi {{ $panel['tone'] === 'accent' ? 'bi-arrow-down-circle' : 'bi-check2-circle' }}"></i>
                            <span>{{ $panel['empty'] }}</span>
                        </div>
                    @endforelse
                </x-ui.panel>
            </div>
        @endforeach
    </div>
@endif

{{-- ── Performance overview (managers / admin / HR) ─────────────────────── --}}
@if($evaluatedInterns->count() > 0)
    <div class="row g-3 mb-4">
        <div class="col-12 fade-in">
            <div class="card card-soft">
                <div class="card-body">
                    <x-ui.section-title icon="bi-activity">Performance des stagiaires</x-ui.section-title>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Stagiaire</th>
                                    <th style="width:200px">Score</th>
                                    <th>Statut</th>
                                    <th class="text-end">Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($evaluatedInterns as $intern)
                                    @php $score = $intern->performanceScore(); @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $intern->user?->full_name ?? 'Non lié' }}</td>
                                        <td>
                                            @if($score['has_data'] ?? false)
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1">
                                                        <div class="progress-bar bg-{{ $score['badge'] }}" style="width: {{ $score['score'] }}%"></div>
                                                    </div>
                                                    <span class="fw-semibold font-monospace small">{{ $score['score'] }}</span>
                                                </div>
                                            @else
                                                <span class="text-muted small">Aucun rapport</span>
                                            @endif
                                        </td>
                                        <td><span class="badge text-bg-{{ $score['badge'] }}">{{ $score['label'] }}</span></td>
                                        <td class="text-end">
                                            @if(auth()->user()->hasRole('Administrateur', 'Responsable RH', 'Responsable de competence'))
                                                <a href="{{ route('interns.show', $intern) }}" class="btn btn-sm btn-outline-primary">Voir</a>
                                            @elseif(auth()->user()->hasRole('Encadrant'))
                                                <a href="{{ route('supervisor.interns.show', $intern) }}" class="btn btn-sm btn-outline-primary">Voir</a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($hasMoreInterns && $internsListUrl)
                        <div class="text-center mt-3">
                            <a href="{{ $internsListUrl }}" class="btn btn-sm btn-outline-secondary">Voir plus <i class="bi bi-arrow-right"></i></a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ── Recent activity ─────────────────────────────────────────────────── --}}
<div class="row g-3">
    @if($canViewTasks)
        <div class="col-lg-6 fade-in">
            <div class="card card-soft h-100">
                <div class="card-body">
                    <x-ui.section-title icon="bi-clock-history">Tâches récentes</x-ui.section-title>
                    <div class="d-grid gap-2">
                        @forelse($latestTasks as $task)
                            <div class="d-flex justify-content-between align-items-start gap-3 pb-2 border-bottom">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-break">{{ $task->title }}</div>
                                    <small class="text-muted">{{ $task->assignedTo?->full_name ?? '-' }}</small>
                                </div>
                                <span class="flex-shrink-0">@statusBadge($task->status)</span>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Aucune tâche récente.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="col-lg-6 fade-in">
        <div class="card card-soft h-100">
            <div class="card-body">
                <x-ui.section-title icon="bi-inbox">Demandes récentes</x-ui.section-title>
                <div class="d-grid gap-2">
                    @forelse($latestRequests as $requestItem)
                        <div class="d-flex justify-content-between align-items-start gap-3 pb-2 border-bottom">
                            <div class="min-w-0">
                                <div class="fw-semibold text-break">{{ $requestItem->intern->user?->full_name ?? 'Non lié' }}</div>
                                <small class="text-muted">{{ $requestItem->workflow_status ? \App\Support\AttestationWorkflow::shortLabel($requestItem->workflow_status) : ucfirst($requestItem->type) }}</small>
                            </div>
                            <span class="flex-shrink-0">@statusBadge($requestItem->status)</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Aucune demande récente.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
