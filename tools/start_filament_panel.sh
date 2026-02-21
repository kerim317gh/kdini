#!/bin/zsh
set -euo pipefail

repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
admin_dir="$repo_dir/filament-admin"
port="${1:-8890}"
url="http://127.0.0.1:${port}/admin/login"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP is not installed. Install it first (brew install php)."
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "Composer is not installed. Install it first (brew install composer)."
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "npm is not installed. Install Node.js first."
  exit 1
fi

if [[ ! -d "$admin_dir" ]]; then
  echo "Missing Filament app at: $admin_dir"
  exit 1
fi

cd "$admin_dir"

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

if [[ ! -f database/database.sqlite ]]; then
  mkdir -p database
  touch database/database.sqlite
fi

if [[ ! -d vendor ]]; then
  composer install --no-interaction --prefer-dist
fi

if [[ ! -d node_modules ]]; then
  npm install
fi

if [[ ! -f public/build/manifest.json ]]; then
  npm run build
fi

if [[ ! -f public/css/filament/filament/app.css ]]; then
  php artisan filament:assets
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan migrate --force

admin_email="${ADMIN_EMAIL:-www.kerim317gh@gmail.com}"
admin_password="${ADMIN_PASSWORD:-Kerim@2026!}"
admin_name="${ADMIN_NAME:-kerim317gh}"

php artisan tinker --execute="
use App\\Models\\User;
if (! User::where('email', '$admin_email')->exists()) {
    User::create([
        'name' => '$admin_name',
        'email' => '$admin_email',
        'password' => '$admin_password',
    ]);
    echo 'Admin user created: $admin_email';
} else {
    echo 'Admin user already exists: $admin_email';
}
"

echo "\nFilament panel is starting at ${url}"
if command -v open >/dev/null 2>&1; then
  open "$url" >/dev/null 2>&1 || true
fi

php artisan serve --host=127.0.0.1 --port="$port"
