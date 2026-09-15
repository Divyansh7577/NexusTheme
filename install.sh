#!/usr/bin/env bash
#
# NexusTheme Ubuntu installer
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/Divyansh7577/NexusTheme/main/install.sh \
#     -o /tmp/nexus-theme-install.sh
#   sudo bash /tmp/nexus-theme-install.sh
#
# Optional:
#   sudo PANEL_DIR=/var/www/pterodactyl bash /tmp/nexus-theme-install.sh

set -Eeuo pipefail

readonly REPO_ARCHIVE="${NEXUS_REPO_ARCHIVE:-https://github.com/Divyansh7577/NexusTheme/archive/refs/heads/main.tar.gz}"
readonly PANEL_DIR="${PANEL_DIR:-/var/www/pterodactyl}"
readonly BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/nexustheme}"
readonly TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
readonly WORK_DIR="$(mktemp -d -t nexustheme.XXXXXX)"
readonly BACKUP_DIR="${BACKUP_ROOT}/${TIMESTAMP}"

cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

log() {
    printf '\033[1;36m[NexusTheme]\033[0m %s\n' "$1"
}

warn() {
    printf '\033[1;33m[NexusTheme warning]\033[0m %s\n' "$1" >&2
}

fail() {
    printf '\033[1;31m[NexusTheme error]\033[0m %s\n' "$1" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1. Install it with: sudo apt-get install -y $1"
}

[[ "$EUID" -eq 0 ]] || fail "Run this installer as root: sudo bash install.sh"
[[ -f "$PANEL_DIR/artisan" ]] || fail "Pterodactyl was not found at $PANEL_DIR. Set PANEL_DIR to the panel directory."
[[ -f "$PANEL_DIR/composer.json" ]] || fail "No composer.json found in $PANEL_DIR. Aborting."

require_command curl
require_command tar
require_command python3
require_command php

PANEL_OWNER="$(stat -c '%U:%G' "$PANEL_DIR")"
PANEL_USER="${PANEL_OWNER%%:*}"

if [[ "$PANEL_USER" == "root" ]]; then
    warn "The panel directory is owned by root. The installer will preserve that owner."
fi

log "Backing up existing NexusTheme files and panel integration points"
mkdir -p "$BACKUP_DIR"
backup_if_present() {
    local relative_path="$1"
    if [[ -e "$PANEL_DIR/$relative_path" ]]; then
        mkdir -p "$BACKUP_DIR/$(dirname "$relative_path")"
        cp -a "$PANEL_DIR/$relative_path" "$BACKUP_DIR/$relative_path"
    fi
}

backup_if_present "public/themes/nexustheme"
backup_if_present "resources/views/layouts/master.blade.php"
backup_if_present "resources/views/templates/wrapper.blade.php"
backup_if_present "config/app.php"
backup_if_present "app/Providers/RouteServiceProvider.php"
backup_if_present "app/PluginController.php"
backup_if_present "resources/views/server/console.blade.php"
backup_if_present "resources/views/server/index.blade.php"

log "Downloading NexusTheme"
curl --fail --silent --show-error --location "$REPO_ARCHIVE" -o "$WORK_DIR/nexustheme.tar.gz"
tar -xzf "$WORK_DIR/nexustheme.tar.gz" -C "$WORK_DIR"
SOURCE_DIR="$(find "$WORK_DIR" -mindepth 1 -maxdepth 1 -type d -name 'NexusTheme-*' -print -quit)"
[[ -n "$SOURCE_DIR" && -d "$SOURCE_DIR" ]] || fail "The NexusTheme archive did not contain the expected source directory."

log "Installing theme files"
install -d "$PANEL_DIR/public/themes/nexustheme"
install -d "$PANEL_DIR/resources/views/components"
install -d "$PANEL_DIR/resources/views/layouts"
install -d "$PANEL_DIR/app/Contracts"
install -d "$PANEL_DIR/app/Http/Controllers"
install -d "$PANEL_DIR/app/Providers"
install -d "$PANEL_DIR/app/Services/NexusTheme"
install -d "$PANEL_DIR/config"
install -d "$PANEL_DIR/routes"

cp -a "$SOURCE_DIR/public/themes/nexustheme/." "$PANEL_DIR/public/themes/nexustheme/"
cp -f "$SOURCE_DIR/resources/views/components/nexus-server-tools.blade.php" "$PANEL_DIR/resources/views/components/"
cp -f "$SOURCE_DIR/resources/views/layouts/nexus-theme-inject.blade.php" "$PANEL_DIR/resources/views/layouts/"
cp -f "$SOURCE_DIR/app/Contracts/NexusServerGateway.php" "$PANEL_DIR/app/Contracts/"
cp -f "$SOURCE_DIR/app/Http/Controllers/NexusThemeController.php" "$PANEL_DIR/app/Http/Controllers/"
cp -f "$SOURCE_DIR/app/Providers/NexusThemeServiceProvider.php" "$PANEL_DIR/app/Providers/"
cp -f "$SOURCE_DIR/app/Services/NexusTheme/PterodactylWingsGateway.php" "$PANEL_DIR/app/Services/NexusTheme/"
cp -f "$SOURCE_DIR/config/nexus-theme.php" "$PANEL_DIR/config/"
cp -f "$SOURCE_DIR/routes/nexus-theme.php" "$PANEL_DIR/routes/"

patch_file() {
    local file="$1"
    shift
    python3 - "$file" "$@" <<'PY'
import pathlib
import sys

path = pathlib.Path(sys.argv[1])
operation = sys.argv[2]
text = path.read_text()

if operation == "master":
    marker = "@include('layouts.nexus-theme-inject')"
    if marker not in text:
        if "</head>" not in text:
            raise SystemExit("No </head> marker found")
        text = text.replace("</head>", f"    {marker}\n</head>", 1)

elif operation == "provider":
    marker = "Pterodactyl\\Providers\\NexusThemeServiceProvider::class,"
    if marker not in text:
        anchor = "Pterodactyl\\Providers\\ViewComposerServiceProvider::class,"
        if anchor not in text:
            raise SystemExit("Provider list anchor not found")
        text = text.replace(anchor, f"        {marker}\n{anchor}", 1)

elif operation == "routes":
    marker = "base_path('routes/nexus-theme.php')"
    if marker not in text:
        lines = text.splitlines(keepends=True)
        daemon_index = next(
            (index for index, line in enumerate(lines) if "Route::middleware('daemon')" in line),
            None,
        )
        if daemon_index is None:
            raise SystemExit("Route provider anchor not found")
        indent = lines[daemon_index][:len(lines[daemon_index]) - len(lines[daemon_index].lstrip())]
        snippet = (
            f"{indent}Route::middleware(['api', RequireTwoFactorAuthentication::class])\n"
            f"{indent}    ->group(base_path('routes/nexus-theme.php'));\n\n"
        )
        lines.insert(daemon_index, snippet)
        text = "".join(lines)

elif operation == "server":
    marker = "@include('components.nexus-server-tools'"
    if marker not in text:
        include_line = "    @include('components.nexus-server-tools', ['server' => $server])"
        if "@section('content')" in text:
            text = text.replace("@section('content')", "@section('content')\n" + include_line, 1)
        elif "@endsection" in text:
            text = text.replace("@endsection", include_line + "\n@endsection", 1)
        else:
            raise SystemExit("No Blade content section found")

else:
    raise SystemExit(f"Unknown patch operation: {operation}")

path.write_text(text)
PY
}

log "Registering the theme service provider"
patch_file "$PANEL_DIR/config/app.php" provider

log "Registering authenticated NexusTheme routes"
patch_file "$PANEL_DIR/app/Providers/RouteServiceProvider.php" routes

repair_blueprint_psr4() {
    local misplaced_controller="$PANEL_DIR/app/PluginController.php"
    local expected_directory="$PANEL_DIR/app/BlueprintFramework/Extensions/modrinthbrowser"
    local expected_controller="$expected_directory/PluginController.php"

    if [[ ! -f "$misplaced_controller" ]]; then
        return 0
    fi

    if grep -Eq 'namespace[[:space:]]+Pterodactyl\\BlueprintFramework\\Extensions\\modrinthbrowser;' "$misplaced_controller"; then
        if [[ -e "$expected_controller" ]]; then
            warn "Blueprint controller already exists at $expected_controller; leaving $misplaced_controller unchanged."
            return 0
        fi
        install -d "$expected_directory"
        mv "$misplaced_controller" "$expected_controller"
        log "Moved Blueprint PluginController.php into its PSR-4 directory"
    fi
}

repair_blueprint_psr4

find_view() {
    local candidate
    if [[ -n "${NEXUS_MASTER_VIEW:-}" && -f "$PANEL_DIR/$NEXUS_MASTER_VIEW" ]]; then
        printf '%s' "$PANEL_DIR/$NEXUS_MASTER_VIEW"
        return 0
    fi
    for candidate in \
        "resources/views/layouts/master.blade.php" \
        "resources/views/templates/wrapper.blade.php"; do
        if [[ -f "$PANEL_DIR/$candidate" ]]; then
            printf '%s' "$PANEL_DIR/$candidate"
            return 0
        fi
    done
    return 1
}

if MASTER_VIEW="$(find_view)"; then
    log "Injecting NexusTheme assets into $MASTER_VIEW"
    patch_file "$MASTER_VIEW" master
else
    warn "Could not find the panel master Blade view. Add @include('layouts.nexus-theme-inject') before </head> manually."
fi

SERVER_VIEW="${NEXUS_SERVER_VIEW:-}"
if [[ -n "$SERVER_VIEW" && -f "$PANEL_DIR/$SERVER_VIEW" ]]; then
    SERVER_VIEW="$PANEL_DIR/$SERVER_VIEW"
else
    SERVER_VIEW=""
    for candidate in \
        "resources/views/server/console.blade.php" \
        "resources/views/server/index.blade.php" \
        "resources/views/templates/server.blade.php"; do
        if [[ -f "$PANEL_DIR/$candidate" ]]; then
            SERVER_VIEW="$PANEL_DIR/$candidate"
            break
        fi
    done
fi

if [[ -n "$SERVER_VIEW" ]]; then
    log "Adding Nexus server controls to $SERVER_VIEW"
    patch_file "$SERVER_VIEW" server
else
    warn "Could not find a server Blade view. The theme assets and API are installed, but add this manually to your server view:"
    printf "    @include('components.nexus-server-tools', ['server' => \\$server])\n"
fi

log "Applying panel ownership and permissions"
chown -R "$PANEL_OWNER" "$PANEL_DIR/public/themes/nexustheme"
chmod -R u=rwX,go=rX "$PANEL_DIR/public/themes/nexustheme"
chown "$PANEL_OWNER" \
    "$PANEL_DIR/resources/views/components/nexus-server-tools.blade.php" \
    "$PANEL_DIR/resources/views/layouts/nexus-theme-inject.blade.php" \
    "$PANEL_DIR/app/Contracts/NexusServerGateway.php" \
    "$PANEL_DIR/app/Http/Controllers/NexusThemeController.php" \
    "$PANEL_DIR/app/Providers/NexusThemeServiceProvider.php" \
    "$PANEL_DIR/app/Services/NexusTheme/PterodactylWingsGateway.php" \
    "$PANEL_DIR/config/nexus-theme.php" \
    "$PANEL_DIR/routes/nexus-theme.php"

run_as_panel_user() {
    if [[ "$PANEL_USER" == "root" ]]; then
        "$@"
    else
        sudo -u "$PANEL_USER" "$@"
    fi
}

log "Refreshing Composer autoload and Laravel caches"
if command -v composer >/dev/null 2>&1; then
    run_as_panel_user composer dump-autoload --no-interaction --quiet
else
    warn "Composer was not found. Run 'sudo -u $PANEL_USER composer dump-autoload' before using the panel."
fi
run_as_panel_user php "$PANEL_DIR/artisan" optimize:clear

log "NexusTheme installation complete"
printf '\nBackup: %s\n' "$BACKUP_DIR"
printf 'Panel:  %s\n' "$PANEL_DIR"
printf '\nNext steps:\n'
printf '1. Add provider values to %s/.env if you use CurseForge, Fabric, Forge, or Pufferfish.\n' "$PANEL_DIR"
printf '2. Sign in to a server page and hard-refresh your browser.\n'
printf '3. If the installer warned about a Blade view, follow the manual include command above.\n'