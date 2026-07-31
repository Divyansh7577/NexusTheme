# NexusTheme — Neon Blue Custom Theme for Pterodactyl Panel

Ye repo Pterodactyl panel ke liye ek custom neon-blue theme deta hai.
Isme panel ke original `master.blade.php` ko **poora replace nahi** kiya gaya —
kyunki Pterodactyl har version update mein us file ko badal deta hai, agar
hum poori file overwrite karenge to next update pe panel crash/break ho sakta hai.

Isliye safe tarika ye hai: **ek chhota CSS-injection snippet** panel ke existing
`master.blade.php` ke `<head>` section mein add karna. Neeche step-by-step
process hai.

## Repo Structure

```
NexusTheme/
├── public/themes/nexustheme/css/neon-blue.css   <-- Main theme CSS
├── resources/views/layouts/nexus-theme-inject.blade.php <-- Include snippet
└── README.md
```

---

## Step 1: GitHub Repository Setup

1. GitHub par naya repository banao (e.g. `NexusTheme`), Public ya Private — dono chalega.
2. Apne phone/PC se ye files upload karo (GitHub app ya web "Add file → Upload files" se):
   - `public/themes/nexustheme/css/neon-blue.css`
   - `resources/views/layouts/nexus-theme-inject.blade.php`
   - `README.md`
3. Folder structure exactly wahi rakhna jo upar diya hai, taaki VPS pe copy karte waqt paths match ho.
4. Commit karke push kar do.

---

## Step 2: Pterodactyl VPS Par Clone/Download Karna

Apne VPS mein SSH se login karo, phir panel ki directory mein jao (usually):

```bash
cd /var/www/pterodactyl
```

Ab apna theme repo temporarily clone karo:

```bash
cd /tmp
git clone https://github.com/<your-username>/NexusTheme.git
```

Ab files ko sahi jagah copy karo:

```bash
# CSS folder banao agar nahi hai
mkdir -p /var/www/pterodactyl/public/themes/nexustheme/css

# CSS file copy karo
cp /tmp/NexusTheme/public/themes/nexustheme/css/neon-blue.css /var/www/pterodactyl/public/themes/nexustheme/css/neon-blue.css
```

Permissions sahi rakhna zaroori hai (Pterodactyl `www-data` user use karta hai generally):

```bash
chown -R www-data:www-data /var/www/pterodactyl/public/themes
chmod -R 755 /var/www/pterodactyl/public/themes
```

---

## Step 3: master.blade.php Mein Link Add Karna

Panel ka main layout file yahan hota hai:

```bash
nano /var/www/pterodactyl/resources/views/layouts/master.blade.php
```

Is file ko open karke `</head>` tag DHOOND (search) karo. Uske **thik pehle**
ye ek line add karo:

```blade
@include('layouts.nexus-theme-inject')
```

Phir `nexus-theme-inject.blade.php` file ko bhi sahi jagah copy karo:

```bash
cp /tmp/NexusTheme/resources/views/layouts/nexus-theme-inject.blade.php /var/www/pterodactyl/resources/views/layouts/nexus-theme-inject.blade.php
```

Save karo (`nano` mein: `Ctrl+O`, Enter, phir `Ctrl+X` exit ke liye).

---

## Step 4: Cache Clear Karke Theme Activate Karna

Pterodactyl root directory mein jao aur ye commands chalao (root ya sudo user se):

```bash
cd /var/www/pterodactyl

php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

Agar `www-data` user ke through commands chalane hain (recommended, permission issues avoid karne ke liye):

```bash
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan cache:clear
```

Ab apna panel browser mein hard refresh karo:

- **Windows/Linux**: `Ctrl + Shift + R`
- **Mac**: `Cmd + Shift + R`
- **Mobile**: Browser settings se cache clear karo ya incognito mein open karo

---

## Step 5: Verify

Login page ya dashboard neon blue glow ke saath dikhna chahiye — sidebar,
buttons, cards sab pe cyan/blue neon accents honge.

Agar CSS load nahi ho raha:

```bash
# Check karo file exist karti hai ya nahi
ls -la /var/www/pterodactyl/public/themes/nexustheme/css/

# Browser console (F12) mein 404 error check karo neon-blue.css ke liye
```

Agar 404 aa raha hai, to path mismatch hai — confirm karo ki
`asset('themes/nexustheme/css/neon-blue.css')` wahi path point kar raha hai
jahan file actually copy hui hai.

---

## Important Notes

- Jab bhi Pterodactyl panel ko `php artisan p:upgrade` se update karoge,
  `master.blade.php` reset ho sakti hai — us case mein sirf **Step 3**
  (ek line `@include`) dobara add karni hogi. Baaki CSS file safe rahegi
  kyunki wo `public/` folder mein hai jo update se generally untouched rehta hai.
- Colors change karne ke liye `neon-blue.css` ke top mein diye `:root` variables
  (`--nexus-neon`, `--nexus-bg`, etc.) edit karo — poori file dobara likhne ki
  zaroorat nahi.
