{{-- Render the server tools beside Pterodactyl's React root on modern panels. --}}
@if(request()->is('server/*') && !isset($server))
    @include('components.nexus-server-tools', ['server' => request()->segment(2)])
@endif