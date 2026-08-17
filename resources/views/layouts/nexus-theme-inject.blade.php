{{-- NexusTheme injection partial. Include this once before </head> in master.blade.php. --}}
<link rel="stylesheet" href="{{ asset('themes/nexustheme/css/neon-blue.css') }}">
<script
    id="nexus-theme-script"
    src="{{ asset('themes/nexustheme/js/nexus-panel.js') }}"
    data-api-base="{{ url('/api/client/servers') }}"
    data-server-uuid="{{ isset($server) ? ($server->uuidShort ?? $server->uuid) : '' }}"
    data-csrf-token="{{ csrf_token() }}"
    defer
></script>