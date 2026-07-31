# NexusTheme — Neon Blue Theme for Pterodactyl Panel

Free, public-use custom theme for Pterodactyl Panel.

## Installation

### Step 1: Clone the repo on your VPS

```bash
cd /tmp
git clone https://github.com/Divyansh7577/NexusTheme.git
```

### Step 2: Copy the CSS file

```bash
mkdir -p /var/www/pterodactyl/public/themes/nexustheme/css
cp /tmp/NexusTheme/public/themes/nexustheme/css/neon-blue.css /var/www/pterodactyl/public/themes/nexustheme/css/neon-blue.css
```

### Step 3: Set permissions

```bash
chown -R www-data:www-data /var/www/pterodactyl/public/themes
chmod -R 755 /var/www/pterodactyl/public/themes
```

### Step 4: Copy the inject snippet

```bash
cp /tmp/NexusTheme/resources/views/layouts/nexus-theme-inject.blade.php /var/www/pterodactyl/resources/views/layouts/nexus-theme-inject.blade.php
```

### Step 5: Add one line to master.blade.php

```bash
nano /var/www/pterodactyl/resources/views/layouts/master.blade.php
```

Paste this line right **before** the `</head>` tag:

```blade
@include('layouts.nexus-theme-inject')
```

Save: `Ctrl+O` → Enter → `Ctrl+X`

### Step 6: Clear cache

```bash
cd /var/www/pterodactyl
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Step 7: Hard refresh your browser

- Windows/Linux: `Ctrl + Shift + R`
- Mac: `Cmd + Shift + R`

The theme should now be active — neon blue glow on the sidebar, buttons, cards, and tables.

## Note

Running a panel upgrade (`php artisan p:upgrade`) may reset `master.blade.php` — if that happens, just redo **Step 5**.
