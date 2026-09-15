# NexusTheme

NexusTheme is an additive Pterodactyl panel theme with a deep dark
glassmorphism interface, Neon Blue `#06b6d4`, and Vibrant Purple `#8b5cf6`.
It adds the Nexus server control plane without replacing Pterodactyl's core
application files.

## Fastest Ubuntu installation

The installer is designed for an Ubuntu VM with an existing Pterodactyl panel.
It creates a timestamped backup, downloads the current `main` branch, installs
the theme files, registers the service provider and authenticated routes,
injects the Blade assets, refreshes Composer/Laravel caches, and prints any
manual step it could not safely detect.

Run these commands as a sudo-capable user:

```bash
sudo apt-get update
sudo apt-get install -y curl tar python3 php-cli

curl -fsSL \
  https://raw.githubusercontent.com/Divyansh7577/NexusTheme/main/install.sh \
  -o /tmp/nexus-theme-install.sh

sudo bash /tmp/nexus-theme-install.sh
```

If the panel is not installed at `/var/www/pterodactyl`, pass its location:

```bash
sudo PANEL_DIR=/srv/pterodactyl bash /tmp/nexus-theme-install.sh
```

The installer does not overwrite a previous backup. Backups are stored under:

```text
/var/backups/nexustheme/<UTC-timestamp>/
```

## Manual Ubuntu installation

Use this method when you want to inspect every file before installing.

### 1. Confirm the panel directory

```bash
export PANEL_DIR=/var/www/pterodactyl

test -f "$PANEL_DIR/artisan" || {
  echo "Pterodactyl was not found at $PANEL_DIR"
  exit 1
}
```

### 2. Back up the panel files that will change

```bash
export BACKUP_DIR="/var/backups/nexustheme/$(date -u +%Y%m%dT%H%M%SZ)"
sudo mkdir -p "$BACKUP_DIR"

sudo cp -a "$PANEL_DIR/config/app.php" "$BACKUP_DIR/" 2>/dev/null || true
sudo cp -a "$PANEL_DIR/app/Providers/RouteServiceProvider.php" "$BACKUP_DIR/" 2>/dev/null || true
sudo cp -a "$PANEL_DIR/resources/views/layouts/master.blade.php" "$BACKUP_DIR/" 2>/dev/null || true
sudo cp -a "$PANEL_DIR/resources/views/templates/wrapper.blade.php" "$BACKUP_DIR/" 2>/dev/null || true
sudo cp -a "$PANEL_DIR/.env" "$BACKUP_DIR/" 2>/dev/null || true
```

### 3. Download the repository

```bash
cd /tmp
rm -rf NexusTheme
git clone --depth 1 --branch main \
  https://github.com/Divyansh7577/NexusTheme.git NexusTheme
cd NexusTheme
```

### 4. Copy the theme and PHP integration files

```bash
sudo install -d \
  "$PANEL_DIR/public/themes/nexustheme" \
  "$PANEL_DIR/resources/views/components" \
  "$PANEL_DIR/resources/views/layouts" \
  "$PANEL_DIR/app/Contracts" \
  "$PANEL_DIR/app/Http/Controllers" \
  "$PANEL_DIR/app/Providers" \
  "$PANEL_DIR/app/Services/NexusTheme" \
  "$PANEL_DIR/config" \
  "$PANEL_DIR/routes"

sudo cp -a public/themes/nexustheme/. \
  "$PANEL_DIR/public/themes/nexustheme/"
sudo cp resources/views/components/nexus-server-tools.blade.php \
  "$PANEL_DIR/resources/views/components/"
sudo cp resources/views/layouts/nexus-theme-inject.blade.php \
  "$PANEL_DIR/resources/views/layouts/"
sudo cp app/Contracts/NexusServerGateway.php \
  "$PANEL_DIR/app/Contracts/"
sudo cp app/Http/Controllers/NexusThemeController.php \
  "$PANEL_DIR/app/Http/Controllers/"
sudo cp app/Providers/NexusThemeServiceProvider.php \
  "$PANEL_DIR/app/Providers/"
sudo cp app/Services/NexusTheme/PterodactylWingsGateway.php \
  "$PANEL_DIR/app/Services/NexusTheme/"
sudo cp config/nexus-theme.php "$PANEL_DIR/config/"
sudo cp routes/nexus-theme.php "$PANEL_DIR/routes/"
```

### 5. Register the Nexus service provider

Open the provider list:

```bash
sudo nano "$PANEL_DIR/config/app.php"
```

Add this entry inside the `providers` array:

```php
Pterodactyl\Providers\NexusThemeServiceProvider::class,
```

The included adapter uses Pterodactyl's own `DaemonFileRepository` for remote
file pulls and log reads. It never gives the browser direct filesystem or
Wings credentials.

### 6. Register the authenticated routes

Open the route provider:

```bash
sudo nano "$PANEL_DIR/app/Providers/RouteServiceProvider.php"
```

Inside the `routes()` method, after the existing `api-client.php` route group,
add:

```php
Route::middleware(['api', RequireTwoFactorAuthentication::class])
    ->group(base_path('routes/nexus-theme.php'));
```

The included route file adds client API, throttling, server access, and
subuser authorization middleware for the server-specific Nexus endpoints.

### 7. Inject the theme assets

Find the panel's main Blade layout. On many versions it is one of:

```text
resources/views/layouts/master.blade.php
resources/views/templates/wrapper.blade.php
```

Immediately before `</head>`, add:

```blade
@include('layouts.nexus-theme-inject')
```

### 8. Add the server tools component

Find the Blade view used for the server page. If your panel has
`resources/views/server/console.blade.php`, add this inside its content section:

```blade
@include('components.nexus-server-tools', ['server' => $server])
```

If your Pterodactyl version uses a React-only server view, keep the theme
assets and API installation, then add the component include to the Blade
wrapper used by your server route. The installer prints this manual step when
it cannot identify the correct view automatically.

### 9. Configure optional providers

Back up and open the environment file:

```bash
sudo cp "$PANEL_DIR/.env" "$PANEL_DIR/.env.nexustheme.backup"
sudo nano "$PANEL_DIR/.env"
```

Add only the values you need:

```dotenv
# Required only for CurseForge searches.
CURSEFORGE_API_KEY=

# Required when using the corresponding platform resolver.
NEXUS_FABRIC_DOWNLOAD_URL=
NEXUS_FORGE_DOWNLOAD_URL=
NEXUS_PUFFERFISH_DOWNLOAD_URL=
```

Modrinth, Hangar, PaperMC, Purpur, and GeyserMC use their public official
APIs. Never commit provider keys to this repository.

### 10. Refresh Composer, permissions, and Laravel caches

```bash
cd "$PANEL_DIR"

sudo composer dump-autoload
sudo php artisan optimize:clear
sudo chown -R www-data:www-data public/themes/nexustheme
sudo chmod -R u=rwX,go=rX public/themes/nexustheme
```

If your panel uses a different owner, replace `www-data:www-data` with the
owner shown by:

```bash
stat -c '%U:%G' "$PANEL_DIR"
```

### 11. Verify the installation

```bash
cd "$PANEL_DIR"
sudo php artisan route:list | grep nexus
test -f public/themes/nexustheme/css/neon-blue.css
test -f public/themes/nexustheme/js/nexus-panel.js
```

Sign in, open a server page, and hard-refresh the browser:

```text
Ctrl + Shift + R
```

## Included files

- `install.sh` — guarded Ubuntu installer with backups and automatic
  registration.
- `public/themes/nexustheme/css/neon-blue.css` — scoped visual theme and
  responsive control surface.
- `public/themes/nexustheme/js/nexus-panel.js` — same-origin client for
  provider search, installs, version switching, Geyser updates, activity
  feedback, and assistant commands.
- `resources/views/components/nexus-server-tools.blade.php` — server tools UI.
- `resources/views/layouts/nexus-theme-inject.blade.php` — safe layout include.
- `routes/nexus-theme.php` — authenticated Nexus API routes.
- `app/Http/Controllers/NexusThemeController.php` — provider orchestration.
- `app/Services/NexusTheme/PterodactylWingsGateway.php` — Wings file adapter.

## Uninstall

Use the backup created during installation:

```bash
export PANEL_DIR=/var/www/pterodactyl
export BACKUP_DIR=/var/backups/nexustheme/<UTC-timestamp>

sudo rm -rf "$PANEL_DIR/public/themes/nexustheme"
sudo rm -f \
  "$PANEL_DIR/app/Contracts/NexusServerGateway.php" \
  "$PANEL_DIR/app/Http/Controllers/NexusThemeController.php" \
  "$PANEL_DIR/app/Providers/NexusThemeServiceProvider.php" \
  "$PANEL_DIR/app/Services/NexusTheme/PterodactylWingsGateway.php" \
  "$PANEL_DIR/config/nexus-theme.php" \
  "$PANEL_DIR/routes/nexus-theme.php"
```

Remove the Nexus provider, route registration, and Blade includes from the
files backed up above, then run:

```bash
cd "$PANEL_DIR"
sudo composer dump-autoload
sudo php artisan optimize:clear
```

The implementation is same-origin and CSRF-protected. Provider credentials
stay server-side, and plugin/jar operations only happen through authenticated
server routes backed by Pterodactyl/Wings.