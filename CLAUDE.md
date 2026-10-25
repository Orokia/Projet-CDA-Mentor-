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
    - The `docker` job (`needs: checks`) downloads `app-package` and builds `docker/prod/Dockerfile` (php-fpm only; the build context is the extracted artifact). Trivy scans the image and blocks on CRITICAL/HIGH findings with `ignore-unfixed`. The scanned image is pushed to Docker Hub on every push, except a direct push to `main` or `develop`. The root `Dockerfile` is for dev (Docker Compose) only.
  - `release.yml` runs on `v*` tags. It calls `build.yml` with `secrets: inherit`, then creates the GitHub Release with the `app-package` archive from the same run. Only that last job has `contents: write`. A tag with a suffix such as `-rc.1` becomes a pre-release.
  - `deploy.yml` is triggered manually (`staging` or `production`). It downloads the release archive and verifies its checksum, without rebuilding. Pre-releases are refused in `production`.

## Architecture

**Thin controllers, logic in services.** Each front controller in `src/Controller/` has a matching service in `src/Service/` (`ReservationController` → `ReservationService`, and so on). The controller checks the logged-in `User`, validates request parameters, calls the service, and converts exceptions into flash messages and redirects. Services throw `\RuntimeException` / `\InvalidArgumentException` with French messages that are shown directly to the user. EasyAdmin CRUD controllers live in `src/Controller/Admin/`, and their logic goes in `src/Service/Admin/*CrudService`.

**Account vs. profile.** `User` is the authentication entity (login by `email`, `isVerified` email confirmation through SymfonyCasts VerifyEmail). It has an optional one-to-one link to **either** a `Student` (`user.student`) or a `Formateur` (`user.formateur` ↔ `formateur.compteUtilisateur`). Code needs to handle a `User` that has neither profile. `MentorUserService::build()` flattens a `User` and its profile into the `App\Dto\MentorUser` DTO (plus `MentorStudent` / `MentorFormateurInfo`), which the templates use.

**Booking model.** A `Disponibilite` (availability) belongs to one `Formateur` and one `Formation`. It covers a date period plus a list of weekdays (`joursSemaine`). `ReservationService::creerReservations()` creates **one `Reservation` for each occurrence** of the chosen weekday inside that period, and attaches a Jitsi Meet room URL to each one (`https://meet.jit.si/<room>`; the domain is also exposed as the `jt_domain` parameter from `JT_DOMAINE`). A `Reservation` optionally owns a `Paiement` (one-to-one, cascade persist/remove). `Specialite` groups `Formation`s and is many-to-many with `Formateur`. On the `Specialite` side, the collection property is spelled `fomateur`, and `Formateur::$specialites` uses `mappedBy: 'fomateur'`. Keep that spelling unless you rename both sides and write a migration.

**Security** (`config/packages/security.yaml`): there is one `main` firewall with `App\Security\AppCustomAuthenticator` (form login) and remember-me. The role hierarchy is `ROLE_ADMIN` > `ROLE_FORMATEUR` > `ROLE_USER`. Access rules: `/admin` requires ROLE_ADMIN, `/reservation` requires ROLE_USER, and `/formateur`, `/login` and `/register` are public. Other routes, such as `/student/profil`, check the user inside the controller.

**Templates**: `templates/<controller_name>/`, plus shared partials in `templates/components/` (`navbar`, `footer`, `formateur_card`, `formation_card`). `base.html.twig` is the layout.

**Mail / async**: Mailer uses `MAILER_DSN` (Mailpit in Docker). Messenger uses a Doctrine transport. Only Notifier chat/SMS messages are routed to `async`, so emails are sent synchronously.
