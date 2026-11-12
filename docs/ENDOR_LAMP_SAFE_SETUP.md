# Teleman on Endor LAMP — safe setup

This guide is for the **browser-based** LAMP service on Endor, not a deployment on a public server.

## 1. Mount the project

In Endor > Mount > Existing Service choose **LAMP** and select the **project root** (the folder containing `artisan`, `composer.json`, `public/`, `resources/`).

Use the target path `/mnt/project` for initial inspection (do not prefix `/mnt/project` to an already absolute path). The exact mount path shown by the UI is authoritative. If mounting fails, retry with a smaller folder excluding `node_modules/`, `vendor/`, `.git/`, `storage/logs/`, and build artifacts. Mounting is one-way: changing files inside the pod does **not** update the host repository.

In Endor terminal (Alpine `/bin/sh`; **not** bash):

```sh
ls -la /mnt/project
test -f /mnt/project/artisan && echo 'PROJECT_MOUNT_OK'
php -v
command -v composer || true
command -v npm || true
```

Do not continue until `PROJECT_MOUNT_OK` appears. The host folder and the LAMP container are separate filesystems; a GitHub URL is not automatically mounted into htdocs.

## 2. Build outside Endor if tooling is missing

The browser LAMP image may have PHP 8.3 and MariaDB but no `git`, `curl`, `composer`, or `npm`. **Do not** use `composer install --ignore-platform-reqs` with PHP 7.3: Laravel 12 needs PHP 8.2+. Install dependencies and build assets in an environment supporting PHP 8.2+ and Node, then mount a **prepared copy** containing `vendor/`, `public/build/`, `artisan`, `bootstrap/`, `app/`, `config/`, `database/`, `routes/`, `resources/`, and `storage/`. Never commit `vendor/`, generated `.env`, or credentials to GitHub.

For a prepared working tree, make sure the Apache document root is **Laravel's `public/` directory**, not the project root, to avoid exposing source files and `.env`. If the Endor image cannot change Apache document root, do not expose the app publicly; use a local-only Laravel dev server with `php artisan serve --host=0.0.0.0 --port=8000` and the Endor port UI if available.

## 3. Configure a disposable local database

Once dependencies and the project are accessible, copy `.env.example` to `.env` **in the guest copy**. Set `APP_ENV=local`, `APP_DEBUG=true`, an Endor-local `APP_URL` and DB settings for the **actual MariaDB service** (the IP printed by Endor; do not assume localhost). Use disposable non-production credentials and a dedicated DB. Do not include credentials in commits.

```sh
cd /mnt/project
test -f artisan && test -f vendor/autoload.php || { echo 'Missing Laravel files or dependencies'; exit 1; }
test -f public/build/manifest.json || echo 'WARNING: Vite frontend build missing'
test -f .env || cp .env.example .env
php artisan key:generate
php artisan migrate --no-interaction
php artisan optimize:clear
php artisan route:list
```

Run migrations only against a verified **disposable** database. Never point this sandbox to UGO or another production service. Do not use `--force` until DB_HOST, DB_DATABASE and credentials are verified.

## 4. Verify

Check the Endor embedded browser and `storage/logs/laravel.log` for startup errors. A mounted folder alone does **not** mean the app is deployed. Restart or reload can lose pod data; back up through GitHub (without secrets).

### Why the previous commands failed

- `/path/to/teleman` was a placeholder rather than a real container path.
- `git`, `curl`, `composer`, and `npm` were not available in the Endor shell.
- `artisan` did not exist in the working directory because the source was never mounted.
- The earlier `DB_HOST=127.0.0.1` suggestion may be wrong when MariaDB has its own VM/IP.
- The project needs an Apache document root pointing at `public/`, not the repository root.

Official references: https://docs.endor.dev/faq/general/ and https://docs.endor.dev/cli/volumes/.
