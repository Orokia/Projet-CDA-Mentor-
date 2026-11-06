# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

"Mentor" is a Symfony 7.4 / PHP ≥ 8.2 (CI and Docker use 8.4) web app where students (`Student`) book sessions with trainers (`Formateur`) for trainings (`Formation`). The code, comments, flash messages and UI are written in French; follow that convention.

Frontend: Twig + AssetMapper/importmap (no Node build step), Bootstrap 5, Stimulus, and Turbo. The admin back-office uses EasyAdmin 5.

## Commands

```bash
# Docker dev stack: app (php-fpm), nginx on :8080, MySQL 8 on host :3307, Mailpit UI on :8025 (SMTP :1025)
docker compose up -d --build
docker compose exec app php bin/console <cmd>

# Database
php bin/console doctrine:migrations:migrate
php bin/console make:migration              # after changing src/Entity
php bin/console doctrine:fixtures:load      # src/DataFixtures/AppFixtures.php
php bin/console doctrine:schema:validate

# Checks run by CI
composer validate --no-check-publish
php bin/console lint:container

# Tests (suites: Unit, Integration, Functional — see phpunit.dist.xml)
php bin/phpunit
php bin/phpunit tests/Unit
php bin/phpunit --testsuite Integration
php bin/phpunit tests/Unit/Services/ReservationServiceTest.php
php bin/phpunit --filter testMethodName
```

PHPUnit is configured with `failOnDeprecation`, `failOnNotice` and `failOnWarning`. Any deprecation coming from `src/` fails the run. DAMA DoctrineTestBundle wraps each test in a transaction that is rolled back afterwards.

## Test environment

- Tests run on a SQLite file database, never on MySQL. `phpunit.dist.xml` forces `DATABASE_URL=sqlite:///%kernel.project_dir%/var/%kernel.environment%.db`, which overrides the MySQL URL that Docker injects. `tests/bootstrap.php` drops and recreates the schema once per PHPUnit run, and only on SQLite. Tests must not run DDL themselves.
- The test environment is chosen by `TEST_APP_ENV`, not by `APP_ENV`, because Docker sets `APP_ENV=dev` in the container. It defaults to `test` (database `var/test.db`). `tests/bootstrap.php` copies it into `APP_ENV` and does not load `.env.local` for `test` or `ci`.
- `ci` is a full test environment, used by GitHub Actions with `APP_ENV=ci TEST_APP_ENV=ci` (database `var/ci.db`, variables in `.env.ci`). Each `when@test` block in `config/packages/` carries the `&test_config` YAML anchor and is reused by `when@ci: *test_config`. `config/bundles.php` enables DAMA and Fixtures for `ci`. When you add a test-only config or bundle, enable it for both environments.
- GitHub Actions has three workflows in `.github/workflows/`. Each one sets `permissions: {}` at the top and grants the minimum per job. Actions are pinned by commit SHA.
  - `build.yml` runs on every branch push and can also be called by other workflows (`workflow_call`). Its `checks` job is a matrix with `fail-fast: true` that has three entries: `build-test`, `sonarqube` (Quality Gate with `sonar.qualitygate.wait=true`, configured in `sonar-project.properties`) and `checkmarx` (SAST, fails on the first high-severity finding). The first entry that fails cancels the others, and there is no `continue-on-error`.
    - `build-test` runs `lint:container` and `doctrine:schema:validate --skip-sync` in `ci`, then the three suites. It then builds the production package: `composer install --no-dev`, `asset-map:compile` with a placeholder `DATABASE_URL`, and a `.tar.gz` plus `.sha256` archive uploaded as the `app-package` artifact.
    - The `docker` job (`needs: checks`) downloads `app-package` and builds `docker/prod/Dockerfile` (php-fpm only; the build context is the extracted artifact). Trivy scans the image (vulnerabilities and secrets) and blocks on CRITICAL/HIGH findings with `ignore-unfixed`; `docker/prod/Dockerfile` runs `apk upgrade` so that Alpine fixes released after the base image are included. The scanned image is pushed to Docker Hub on every push, except a direct push to `main` or `develop`. The root `Dockerfile` is for dev (Docker Compose) only.
  - `release.yml` runs on `v*` tags. It calls `build.yml` with `secrets: inherit`, then creates the GitHub Release with the `app-package` archive from the same run. Only that last job has `contents: write`. A tag with a suffix such as `-rc.1` becomes a pre-release.
  - `deploy.yml` is triggered manually (`staging` or `production`). It downloads the release archive and verifies its checksum, without rebuilding. Pre-releases are refused in `production`.

## Architecture

**Thin controllers, logic in services.** Each front controller in `src/Controller/` has a matching service in `src/Service/` (`ReservationController` → `ReservationService`, and so on). The controller checks the logged-in `User`, validates request parameters, calls the service, and converts exceptions into flash messages and redirects. Services throw `\RuntimeException` / `\InvalidArgumentException` with French messages that are shown directly to the user. EasyAdmin CRUD controllers live in `src/Controller/Admin/`, and their logic goes in `src/Service/Admin/*CrudService`.

**Account vs. profile.** `User` is the authentication entity (login by `email`, `isVerified` email confirmation through SymfonyCasts VerifyEmail). It has an optional one-to-one link to **either** a `Student` (`user.student`) or a `Formateur` (`user.formateur` ↔ `formateur.compteUtilisateur`). Code needs to handle a `User` that has neither profile. `MentorUserService::build()` flattens a `User` and its profile into the `App\Dto\MentorUser` DTO (plus `MentorStudent` / `MentorFormateurInfo`), which the templates use.

**Booking model.** A `Disponibilite` (availability) belongs to one `Formateur` and one `Formation`. It covers a date period plus a list of weekdays (`joursSemaine`). `ReservationService::creerReservations()` creates **one `Reservation` for each occurrence** of the chosen weekday inside that period, and attaches a Jitsi Meet room URL to each one (`https://meet.jit.si/<room>`; the domain is also exposed as the `jt_domain` parameter from `JT_DOMAINE`). `joursSemaine` maps each weekday to a status (`disponible` / `plus disponible`); a confirmed booking sets the chosen day to `plus disponible`. A `Reservation` is `confirmée` only once its payment is effective: `Reservation::confirmer(Paiement)` throws `ogicexception` unless the payment is `paye`, `reservationservice::creerreservations()` requires that paid `paiement`, and an `assertcallback` blocks setting `confirmée` in the back-office without a paid payment (reservations already `confirmée` in the database before stripe are left as they are). status values are the `reservation::statut_*` constants.

**Payment funnel (Stripe Checkout).** `POST /reservation/create` no longer books directly: `PaiementService::demarrerPaiement()` checks the slot (`ReservationService::verifierCreneauReservable()`, pessimistic lock), saves a `Paiement` with status `en_attente` and redirects (303) to the Stripe-hosted Checkout page. Stripe sends the student's browser back to `/api/stripe/webhook/paiement/{id}/succes` (a browser return, not a server webhook; ROLE_USER via `access_control`), where `confirmerPaiement()` re-reads the session from Stripe, then creates the reservations (with Jitsi links) and marks the payment `paye`. If the slot was taken meanwhile, the payment is refunded (`rembourse`). `/api/stripe/webhook/paiement/{id}/annule` expires the session. The real Stripe webhook is `POST /api/stripe/webhook` (`StripeWebhookController`, public, authenticated by the `Stripe-Signature` header checked by `App\Paiement\WebhookStripe` with `STRIPE_WEBHOOK_SECRET`): `checkout.session.completed` / `async_payment_succeeded` call `PaiementService::confirmerDepuisStripe()` (same idempotent confirmation as the browser return, so a student who closes the tab still gets the booking) and `checkout.session.expired` marks a pending payment `annule`. It answers 2xx for handled, ignored or final business cases, 400 for a bad signature, and 500 on technical errors so that Stripe retries. One `Paiement` covers all reservations of the chosen day (`Reservation` → `Paiement` is many-to-one). The gateway is `App\Paiement\PasserellePaiementInterface`: `StripeCheckoutPasserelle` (`STRIPE_SECRET_KEY`, `STRIPE_API_BASE`) everywhere except `test`/`ci`, where `config/services.yaml` swaps in `tests/Support/Paiement/PasserellePaiementMock` (an omnipay/common Mock gateway, no network; static state, call `reinitialiser()` in `setUp`). `Specialite` groups `Formation`s and is many-to-many with `Formateur`. On the `Specialite` side, the collection property is spelled `fomateur`, and `Formateur::$specialites` uses `mappedBy: 'fomateur'`. Keep that spelling unless you rename both sides and write a migration.

**Security** (`config/packages/security.yaml`): there is one `main` firewall with `App\Security\AppCustomAuthenticator` (form login) and remember-me. The role hierarchy is `ROLE_ADMIN` > `ROLE_FORMATEUR` > `ROLE_USER`. Access rules: `/admin` requires ROLE_ADMIN, `/reservation` requires ROLE_USER, and `/formateur`, `/login`, `/register`, `/mot-de-passe-oublie` and `/api/stripe/webhook` are public. Forgotten password uses symfonycasts/reset-password-bundle (`MotDePasseOublieController` → `MotDePasseOublieService`, entity `DemandeReinitialisationMotDePasse`, one-hour single-use link, one request per account every 15 minutes, same message whether or not the account exists); password rules are shared in `App\Form\ContraintesMotDePasse`. Other routes, such as `/student/profil`, check the user inside the controller.

**Templates**: `templates/<controller_name>/`, plus shared partials in `templates/components/` (`navbar`, `footer`, `formateur_card`, `formation_card`). `base.html.twig` is the layout.

**Mail / async**: Mailer uses `MAILER_DSN` (Mailpit in Docker). Messenger uses a Doctrine transport. Only Notifier chat/SMS messages are routed to `async`, so emails are sent synchronously.
