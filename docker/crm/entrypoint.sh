#!/bin/sh
set -eu

project=/var/www/html
vendor_dir="$project/vendor"
lock_file="$project/composer.lock"
stamp_file="$vendor_dir/.kpcrm-composer-lock.sha256"

if [ -f "$lock_file" ]; then
    expected_checksum="$(sha256sum "$lock_file" | awk '{print $1}')"
    installed_checksum="$(cat "$stamp_file" 2>/dev/null || true)"

    # Nunca reutilizar dependencias de otro composer.lock. No se borran
    # volúmenes: Composer reconcilia el árbol a partir del lockfile exacto.
    if [ ! -r "$vendor_dir/autoload.php" ] || [ "$expected_checksum" != "$installed_checksum" ]; then
        echo "Installing Composer dependencies for the current composer.lock..."
        composer install --working-dir="$project" --no-interaction --prefer-dist --optimize-autoloader --no-scripts
        printf '%s\n' "$expected_checksum" > "$stamp_file"
    fi
fi

# Los comandos ejecutados como root no deben impedir que Apache (www-data)
# cree sesiones, vistas compiladas o cachés durante la navegación.
mkdir -p "$project/storage" "$project/bootstrap/cache"
chown -R www-data:www-data "$project/storage" "$project/bootstrap/cache"

exec docker-php-entrypoint "$@"
