# Installation

## Quick start for local testing (Docker)

If you just want to run the system locally, you don't need any of the
manual steps below — the compose stack handles dependencies, the asset
build, migrations, and demo seeding on first run:

    docker compose up --build

Then open:

| What | Where |
|------|-------|
| App | http://localhost:8089 (change with `APP_PORT=8090 docker compose up`) |
| MailHog (catches all outbound mail) | http://localhost:8025 |
| MySQL (GUI clients) | `127.0.0.1:33060`, user `root`, password `secret`, db `lawfirm` |

Seeded demo logins (password `E2ePassword!123` for all): `e2e-admin@lawfirm.test`
(admin), `e2e-advocate@lawfirm.test` (advocate), `e2e-clerk@lawfirm.test`
(read-only clerk).

The stack also runs the queue worker (mail/SMS jobs) and the scheduler
(hearing reminders, nightly backup). Common tasks:

    docker compose exec app php artisan test          # backend suite (in-memory sqlite)
    docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
    docker compose exec app vendor/bin/pint --test
    docker compose run --rm assets npm run build      # rebuild frontend after JS/Vue changes
    docker compose down -v                            # stop and wipe the database

This stack is for local testing only — `php artisan serve`, root DB
credentials, and `APP_DEBUG=true` are not a production setup. For
production, follow the manual steps below.

## Prerequisites
1. Node js and npm installed on the server
```sudo apt update```   
```sudo apt install nodejs npm```
```npm i vue```
    ### Confirm Installation
   ```node -v```
   ```npm -v```
2. PHP Installed in the server
3. MySQL installed in the server 

## Step 1: Clone the Repository
    git clone <repo url>

## Step 2: Install Dependencies
    cd <project_repo>
    npm install
    composer install

## Step 3: Configure Environment Variables
    cp .env.example .env
    1. Make sure the mail server is set-up
    2. Change APP_ENV to  production

## Step 4: Build Assets
    npm run prod

## Step 5: Setup Database
    php artisan app:init
    1. Make sure the email in the command CreateAdmin is the admin email

## Step 6: Configure Web Server

    

