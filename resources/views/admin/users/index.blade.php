@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
    <x-page-heading title="Usuarios" subtitle="Administra las cuentas, sus perfiles y su acceso al sistema." eyebrow="ADMINISTRACIÓN"><a class="btn btn-primary" href="{{ route('admin.users.create') }}">Crear cuenta<x-icon name="arrow" /></a></x-page-heading>
    <div class="card">
        <form class="filter-bar" method="GET" action="{{ route('admin.users.index') }}">
            <div class="filter-search"><label class="visually-hidden" for="q">Buscar nombre, correo o
                    carné</label><x-icon name="search" /><input class="form-control" id="q" name="q"
                    value="{{ request('q') }}" placeholder="Nombre, correo o carné" maxlength="80"></div>
            <div><label class="visually-hidden" for="role">Filtrar perfil</label><select class="form-select"
                    id="role" name="role">
                    <option value="">Todos los perfiles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary"
                href="{{ route('admin.users.index') }}">Limpiar</a>
        </form>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Perfil</th>
                        <th>Carné</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td><strong>{{ $user->name }}</strong>
                                <div class="text-secondary">{{ $user->email }}</div>
                            </td>
                            <td>{{ $user->role->label() }}</td>
                            <td>{{ $user->student_code ?? '—' }}</td>
                            <td><span
                                    class="badge-soft {{ $user->active ? '' : 'badge-muted' }}">{{ $user->active ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.users.edit', $user) }}">Editar</a></td>
                    </tr>@empty<tr>
                            <td colspan="5" class="text-secondary text-center py-5">No se encontraron usuarios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $users->links() }}</div>
    </div>
@endsection
