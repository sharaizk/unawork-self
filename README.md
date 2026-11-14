# UnaWork

UnaWork is a Laravel 10 monolith built on top of the Taskly project-management application. The live frontend is primarily Laravel Blade, Bootstrap 5, the Dash admin theme, and jQuery/vanilla JavaScript.

Read [`AGENTS.md`](AGENTS.md) before changing code. It contains the repository architecture, conventions, safety requirements, and verification guidance.

## Local development on macOS with XAMPP

The recommended local setup uses:

- XAMPP for MySQL and phpMyAdmin.
- Homebrew PHP 8.3 for Composer, Artisan, and the local Laravel server.
- The committed frontend assets under `public/assets/`.

Do not use XAMPP's bundled PHP for this application. The current macOS XAMPP PHP 8.2 build does not include the Sodium extension required by `lcobucci/jwt` and Firebase dependencies.

Do not run `composer update` or bypass the requirement with `--ignore-platform-req=ext-sodium`. The versions in `composer.lock` are intentional, and the application needs Sodium at runtime.

### 1. Start XAMPP services

Open XAMPP Manager and start MySQL. Start Apache only if you want to use phpMyAdmin.

Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin) and create a database:

```text
Database: unawork_local
Collation: utf8mb4_unicode_ci
```

The default XAMPP MySQL connection is normally:

```text
Host: 127.0.0.1
Port: 3306
Username: root
Password: empty
```

Use your configured password or port if you changed the XAMPP defaults.

### 2. Install Homebrew PHP 8.3

```bash
brew install php@8.3
```

PHP 8.3 is installed as a versioned, keg-only formula, so it does not need to replace the system-wide PHP executable.

Select it explicitly for the current terminal:

```bash
export UNAWORK_PHP="$(brew --prefix php@8.3)/bin/php"
export UNAWORK_COMPOSER="$(command -v composer)"
```

Verify the PHP version and Sodium extension:

```bash
"$UNAWORK_PHP" -v
"$UNAWORK_PHP" -r 'var_dump(extension_loaded("sodium"));'
```

The second command must print:

```text
bool(true)
```

### 3. Install PHP dependencies

```bash
"$UNAWORK_PHP" "$UNAWORK_COMPOSER" install
```

This installs the exact dependency versions recorded in `composer.lock`.

If Composer reports PHP 8.4 or a missing Sodium extension, it is running under the wrong PHP binary. Run it through `$UNAWORK_PHP` as shown above.

### 4. Create Laravel storage directories

The `storage/` directory is ignored by Git and might not exist in a fresh checkout.

```bash
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs

chmod -R u+rwX storage bootstrap/cache
```

### 5. Configure the environment

```bash
cp .env.example .env
```

Use the following core local settings in `.env`:

```dotenv
APP_NAME="UnaWork"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unawork_local
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8000,localhost:8000

MAIL_MAILER=log
MAIL_FROM_ADDRESS="local@unawork.test"
MAIL_FROM_NAME="${APP_NAME}"

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1
```

Use `SESSION_DRIVER=file` for a fresh local environment because the repository does not include a migration that creates a `sessions` table.

Leave payment, AWS, Firebase, Twilio, Pusher, and other integration credentials empty unless the feature being tested requires them. Never commit `.env` or secrets.

Generate the application key and clear cached configuration:

```bash
"$UNAWORK_PHP" artisan key:generate
"$UNAWORK_PHP" artisan optimize:clear
```

### 6. Prepare the database

#### Recommended: import a sanitized development database

This application contains legacy Taskly settings and reference data that are not fully represented by the main seeder. Ask the team for a sanitized development SQL dump.

Import it with phpMyAdmin or the XAMPP MySQL client:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql \
  -u root \
  unawork_local \
  < "/absolute/path/to/unawork-database.sql"
```

Add `-p` if the MySQL user has a password.

After importing, apply any newer migrations:

```bash
"$UNAWORK_PHP" artisan migrate:status
"$UNAWORK_PHP" artisan migrate
```

Do not run the seeders after importing a complete database dump. Some legacy seeders are not idempotent.

#### Alternative: create a fresh database

Only run the following commands against a new, disposable `unawork_local` database:

```bash
"$UNAWORK_PHP" artisan migrate

"$UNAWORK_PHP" artisan db:seed --class=RolesAndPermissionsSeeder
"$UNAWORK_PHP" artisan db:seed --class=PermissionsSeeder
"$UNAWORK_PHP" artisan db:seed --class=IndustrySeeder
"$UNAWORK_PHP" artisan db:seed --class=JobTitleSeeder
"$UNAWORK_PHP" artisan db:seed
```

These seeders are intended for a fresh database. `RolesAndPermissionsSeeder` uses `Role::create()` and can fail when run more than once.

The default administrator created by `UsersTableSeeder` is:

```text
Email: admin@example.com
Password: 123123123
```

This credential is for local development only. Change the password if the environment is accessible to anyone else.

### 7. Create the public storage link

```bash
"$UNAWORK_PHP" artisan storage:link
```

If you imported a database, obtain the matching uploaded files separately and place them under `storage/app/public`. Uploaded images and documents are not contained in the SQL dump.

### 8. Start the application

Keep XAMPP MySQL running, then start Laravel with Homebrew PHP:

```bash
"$UNAWORK_PHP" artisan serve --host=127.0.0.1 --port=8000
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

Use this server instead of XAMPP Apache so web requests also run under PHP 8.3 with Sodium enabled.

## Frontend assets

No frontend build is required for normal local development. Live pages use committed static assets under `public/assets/`.

Do not run `npm run dev` or `npm run prod` unless the task explicitly requires rebuilding the vestigial Laravel Mix output. Those commands rewrite committed `public/js/app.js` and `public/css/app.css`, which most live screens do not load.

## Common problems

### Composer reports PHP 8.4 incompatibility

Composer is running under the global PHP version. Use:

```bash
"$UNAWORK_PHP" "$UNAWORK_COMPOSER" install
```

### Composer reports that `ext-sodium` is missing

Confirm the selected binary and extension:

```bash
"$UNAWORK_PHP" -v
"$UNAWORK_PHP" --ini
"$UNAWORK_PHP" -r 'var_dump(extension_loaded("sodium"));'
```

Do not use XAMPP PHP or `--ignore-platform-req=ext-sodium`.

### MySQL connection is refused

Confirm MySQL is running in XAMPP and that `.env` contains the correct port. If XAMPP is configured for a non-default port, update `DB_PORT` accordingly.

### Laravel reports that the `sessions` table is missing

Set:

```dotenv
SESSION_DRIVER=file
```

Then clear cached configuration:

```bash
"$UNAWORK_PHP" artisan optimize:clear
```

### Laravel cannot write logs or compiled views

```bash
chmod -R u+rwX storage bootstrap/cache
```

### Images or attachments are missing

Copy the matching development uploads into `storage/app/public`, then run:

```bash
"$UNAWORK_PHP" artisan storage:link
```

## Safety notes

- The `dev` branch deploys to the development server when pushed.
- The `main` branch deploys to production when pushed.
- Never push or merge unless explicitly authorized.
- Deployment automatically runs migrations and caches configuration.
- The PHPUnit configuration is not database-isolated. Do not run the test suite until `.env` is confirmed to use a disposable database.
- Never edit an existing deployed migration; create a new additive migration.
- Never call `env()` outside `config/*.php`.
