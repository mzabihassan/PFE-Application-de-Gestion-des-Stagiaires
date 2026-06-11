@extends('layouts.app')

@section('title', 'Utilisateurs')

@section('content')
<x-ui.page-header title="Gestion des utilisateurs" kicker="Comptes" kicker-icon="bi-people-fill">
    <x-slot:actions>
        <a href="{{ route('users.create') }}" class="btn btn-success btn-sm"><i class="bi bi-person-plus"></i> Nouvel utilisateur</a>
    </x-slot:actions>
</x-ui.page-header>

<div class="card card-soft fade-in">
    <div class="card-body">

        <x-ui.table-toolbar :search="$search" placeholder="Rechercher (nom, email)">
            <select name="role" class="toolbar-select" data-autosubmit aria-label="Filtrer par rôle">
                <option value="">Tous les rôles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected($roleId === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <select name="active" class="toolbar-select" data-autosubmit aria-label="Filtrer par état">
                <option value="">Tous les états</option>
                <option value="1" @selected($active === '1')>Actifs</option>
                <option value="0" @selected($active === '0')>Inactifs</option>
            </select>
        </x-ui.table-toolbar>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>État</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->full_name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role?->name }}</td>
                            <td>
                                @statusBadge($user->is_active ? 'valide' : 'archive')
                            </td>
                            <td class="text-end">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary btn-icon" title="Modifier" aria-label="Modifier"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline" data-confirm="Cet utilisateur sera définitivement supprimé." data-confirm-title="Supprimer l'utilisateur ?" data-confirm-ok="Supprimer">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger btn-icon" type="submit" title="Supprimer" aria-label="Supprimer"><i class="bi bi-trash3"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Aucun utilisateur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>
</div>
@endsection
