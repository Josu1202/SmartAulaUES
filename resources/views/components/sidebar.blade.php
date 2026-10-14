@php($role = auth()->user()->role->value)
<aside class="smartaulaues-sidebar" id="sidebar" aria-label="Menú principal">
    <a class="sidebar-brand" href="{{ route('dashboard') }}" aria-label="{{ config('smartaulaues.name') }}, ir al inicio"><x-logo /></a>
    <div class="sidebar-section-title">ESPACIO ACADÉMICO</div>
    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
            @if (request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="grid" />Inicio</a>
        <a href="{{ route('offerings.index') }}"
            class="sidebar-link {{ request()->routeIs('offerings.*', 'admin.offerings.*') ? 'active' : '' }}"
            @if (request()->routeIs('offerings.*')) aria-current="page" @endif><x-icon
                name="book" />{{ $role === 'student' ? 'Mis materias' : 'Materias y grupos' }}</a>
        @if ($role === 'coordinator')
            <a href="{{ route('admin.subjects.index') }}" class="sidebar-link {{ request()->routeIs('admin.subjects.*') ? 'active' : '' }}"><x-icon name="book" />Catálogo de materias</a>
            <a href="{{ route('admin.cycles.index') }}"
                class="sidebar-link {{ request()->routeIs('admin.cycles.*') ? 'active' : '' }}"><x-icon
                    name="calendar" />Ciclos académicos</a>
            <a href="{{ route('admin.users.index') }}"
                class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><x-icon
                    name="users" />Usuarios</a>
            <a href="{{ route('admin.audit.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"><x-icon name="clock" />Bitácora de cambios</a>
        @endif
        <div class="sidebar-section-title">SEGUIMIENTO</div>
        @foreach (config('modules') as $key => $module)
            @if (in_array($role, $module['roles'], true))
                <a href="{{ $key === 'imports' ? route('admin.imports.index') : route('modules.show', $key) }}"
                    class="sidebar-link {{ (request()->routeIs('modules.show') && request()->route('module') === $key) || ($key === 'imports' && request()->routeIs('admin.imports.*')) ? 'active' : '' }}"><x-icon
                        :name="$module['icon']" />{{ $module['title'] }}</a>
            @endif
        @endforeach
        @if (in_array($role, ['coordinator', 'teacher'], true))
            <div class="sidebar-section-title">TERMINAL MÓVIL</div>
            <a href="{{ route('terminal.index') }}"
                class="sidebar-link {{ request()->routeIs('terminal.*') ? 'active' : '' }}"><x-icon
                    name="nfc" />Prueba del lector NFC</a>
        @endif
    </nav>
    <div class="sidebar-bottom"><span
            class="sidebar-dot"></span>{{ auth()->user()->role->label() }}<small>v{{ config('smartaulaues.version') }}</small>
    </div>
</aside>
