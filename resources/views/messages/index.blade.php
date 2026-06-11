@extends('layouts.app')

@section('title', 'Messagerie')

@section('content')
<x-ui.page-header title="Messagerie interne" kicker="Messages" kicker-icon="bi-chat-dots-fill">
    <x-slot:actions>
        <a href="{{ route('messages.create') }}" class="btn btn-success btn-sm"><i class="bi bi-pencil-square"></i> Nouveau message</a>
    </x-slot:actions>
</x-ui.page-header>

<div class="card card-soft fade-in">
    <div class="card-body">

        <div class="row g-4">
            <div class="col-lg-4">
                <x-ui.table-toolbar :search="$q" search-name="q" placeholder="Rechercher une conversation"
                                    :preserve="['user' => request()->query('user')]" />
                <div class="list-group">
                    @forelse($conversations as $conversation)
                        @php
                            $partner = $conversation['partner'];
                            $lastMessage = $conversation['last_message'];
                        @endphp
                        <a
                            href="{{ route('messages.index', ['user' => $partner?->id]) }}"
                            class="list-group-item list-group-item-action {{ (int) request()->query('user') === (int) $partner?->id ? 'active' : '' }}"
                        >
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">{{ $partner?->full_name ?? 'Utilisateur' }}</div>
                                    <div class="small text-muted text-truncate" style="max-width: 220px;">
                                        {{ $lastMessage?->body }}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">{{ $lastMessage?->created_at?->format('d/m/Y H:i') }}</div>
                                    @if($conversation['unread_count'] > 0)
                                        <span class="badge text-bg-light mt-1">{{ $conversation['unread_count'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="text-muted">Aucune conversation.</div>
                    @endforelse
                </div>
            </div>
            <div class="col-lg-8">
                <div class="border rounded-4 p-3" style="min-height: 420px;">
                    @if($conversationUser)
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-semibold">Conversation avec {{ $conversationUser->full_name }}</div>
                        </div>
                        <div class="vstack gap-2 mb-3" style="max-height: 360px; overflow-y: auto;">
                            @forelse($conversationMessages as $message)
                                @php $isMine = $message->sender_id === auth()->id(); @endphp
                                <div class="d-flex {{ $isMine ? 'justify-content-end' : 'justify-content-start' }}">
                                    <div class="border rounded-3 p-2 {{ $isMine ? 'bg-light' : 'bg-white' }}" style="max-width: 70%;">
                                        <div class="small text-muted">{{ $message->created_at?->format('d/m/Y H:i') }}</div>
                                        <div style="white-space: pre-wrap;">{{ $message->body }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">Aucun message dans cette conversation.</div>
                            @endforelse
                        </div>

                        <form action="{{ route('messages.store') }}" method="POST" class="vstack gap-2">
                            @csrf
                            <input type="hidden" name="receiver_id" value="{{ $conversationUser->id }}">
                            <input type="hidden" name="subject" value="Conversation">
                            <textarea class="form-control" name="body" rows="3" placeholder="Ecrire un message..." required></textarea>
                            <div class="d-flex justify-content-end">
                                <button class="btn btn-primary btn-sm" type="submit">Envoyer</button>
                            </div>
                        </form>
                    @else
                        <div class="text-muted">Selectionnez une conversation pour afficher les messages.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
