# NexusTheme

NexusTheme is an additive Pterodactyl panel theme built around deep dark
glassmorphism, Neon Blue `#06b6d4`, and Vibrant Purple `#8b5cf6`. It does not
replace Pterodactyl's core layout, which keeps panel upgrades safer.

## Included

- `public/themes/nexustheme/css/neon-blue.css` — scoped visual theme and the
  responsive Nexus control surface.
- `public/themes/nexustheme/js/nexus-panel.js` — same-origin client for search,
  install, version switching, Geyser updates, activity feedback, and assistant
  commands.
- `resources/views/components/nexus-server-tools.blade.php` — the server tools
  component.
- `routes/nexus-theme.php` — authenticated route definitions.
- `app/Http/Controllers/NexusThemeController.php` — Modrinth, Hangar,
  CurseForge, PaperMC, Purpur, and GeyserMC provider orchestration.
- `app/Contracts/NexusServerGateway.php` — the small bridge used by the
  controller for privileged node operations.
- `app/Services/NexusTheme/PterodactylWingsGateway.php` — a drop-in adapter
  backed by Pterodactyl's own `DaemonFileRepository`.
- `app/Providers/NexusThemeServiceProvider.php` — binds the adapter.

## Install into Pterodactyl

Copy this repository into the panel source directory, then:

```bash
cp -R public/themes/nexustheme /var/www/pterodactyl/public/themes/
cp -R resources/views/components/nexus-server-tools.blade.php /var/www/pterodactyl/resources/views/components/
cp resources/views/layouts/nexus-theme-inject.blade.php /var/www/pterodactyl/resources/views/layouts/
cp app/Contracts/NexusServerGateway.php /var/www/pterodactyl/app/Contracts/
cp app/Http/Controllers/NexusThemeController.php /var/www/pterodactyl/app/Http/Controllers/
cp app/Services/NexusTheme/PterodactylWingsGateway.php /var/www/pterodactyl/app/Services/NexusTheme/
cp app/Providers/NexusThemeServiceProvider.php /var/www/pterodactyl/app/Providers/
```

Register the routes from `routes/nexus-theme.php` in the panel's authenticated
route provider. Include the injection partial once before `</head>` in
`resources/views/layouts/master.blade.php`:

```blade
@include('layouts.nexus-theme-inject')
```

Include the tools component in the server overview template:

```blade
@include('components.nexus-server-tools', ['server' => $server])
```

## Register the Wings adapter

Register `App\Providers\NexusThemeServiceProvider::class` in the panel's
provider list (`config/app.php` on older Laravel versions). The included
adapter uses Pterodactyl's `DaemonFileRepository` to pull jars into the remote
server directory and read the tail of `logs/latest.log`. No browser-side
filesystem access or second Wings credential is introduced.

## Provider configuration

Modrinth, Hangar, PaperMC, Purpur, and GeyserMC use public official APIs. Add
these only when the corresponding provider is needed:

```dotenv
CURSEFORGE_API_KEY=
NEXUS_FABRIC_DOWNLOAD_URL=
NEXUS_FORGE_DOWNLOAD_URL=
NEXUS_PUFFERFISH_DOWNLOAD_URL=
```

The three platform URL variables allow a host to point Fabric, Forge, and
Pufferfish at its chosen version resolver because their release APIs and
installer flows differ by Minecraft version. The URL must resolve to the
server jar selected by the host.

After installation:

```bash
php artisan view:clear
php artisan cache:clear
php artisan route:clear
```

The implementation is intentionally same-origin and CSRF-protected. Provider
credentials stay server-side, and plugin/jar operations only happen through
the authenticated server route.