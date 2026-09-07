# EVENT MANAGEMENT SYSTEM - AI DEVELOPMENT RULES

## PROJECT OVERVIEW

Build a production-quality Event Management System using Laravel.

The application allows users to discover events, register for events,
receive digital tickets, generate QR Codes, perform QR check-in,
and allows organizers to manage events and participants.

The system must be developed incrementally.

DO NOT build the entire application in one step.

Always inspect the existing project before making changes.

Never overwrite existing functionality unless explicitly requested.

---

# TECHNOLOGY STACK

Backend:
- Laravel 13
- PHP 8.3+
- MySQL
- Eloquent ORM

Frontend:
- Blade
- Livewire
- Tailwind CSS
- Alpine.js

Authentication:
- Official Laravel Starter Kit

Development:
- Laravel Boost
- Vite
- Git

---

# DEVELOPMENT PRINCIPLES

1. Follow Laravel conventions.

2. Use MVC architecture.

3. Use Eloquent relationships instead of raw SQL whenever practical.

4. Use Form Request classes for complex validation.

5. Use Policies/Gates for authorization.

6. Never trust user input.

7. Validate all incoming data.

8. Protect all authenticated routes.

9. Organize routes logically.

10. Avoid unnecessary packages.

11. Do not duplicate code.

12. Create reusable Blade/Livewire components.

13. Keep controllers thin.

14. Put complex business logic into appropriate services/actions.

15. Use database transactions when multiple database operations
   must succeed together.

16. Never expose sensitive environment variables.

17. Never hardcode credentials.

18. Never modify .env.example with real credentials.

---

# USER ROLES

The system has three roles:

ADMIN
ORGANIZER
PARTICIPANT

Role permissions must be enforced on the backend.

Frontend hiding is NOT considered authorization.

Example:

Hiding an "Edit Event" button is not enough.

The backend must also prevent unauthorized users from editing
the event.

---

# DATABASE

Main entities:

User
Event
TicketType
Registration
CheckIn

Relationships:

User:
- hasMany Events as organizer
- hasMany Registrations

Event:
- belongsTo User as organizer
- hasMany TicketTypes
- hasMany Registrations

TicketType:
- belongsTo Event
- hasMany Registrations

Registration:
- belongsTo User
- belongsTo Event
- belongsTo TicketType
- hasOne CheckIn

CheckIn:
- belongsTo Registration
- belongsTo User as checker

Use foreign keys and indexes where appropriate.

Use cascading behavior carefully.

Never create relationships without understanding
their database constraints.

---

# EVENT STATUS

Events should support:

draft
published
ongoing
completed
cancelled

Use a consistent approach for event status.

Do not scatter raw status strings throughout the application.

---

# REGISTRATION FLOW

Participant:

1. Browse event.
2. Open event detail.
3. Select ticket.
4. Register.
5. System validates ticket quota.
6. Registration is created.
7. Unique registration code is generated.
8. QR Code is generated from the registration code.
9. Participant can view the digital ticket.

Prevent duplicate registration for the same event
unless explicitly allowed.

Prevent registration when the event is closed.

Prevent registration when ticket quota is exhausted.

Use database transactions where appropriate.

---

# QR CODE

QR Code must NOT contain sensitive personal data.

The QR Code should contain a unique registration identifier/code.

Example:

EVENT-REG-XXXXXXXX

When scanned:

1. Find registration.
2. Validate registration.
3. Check event status.
4. Check whether participant already checked in.
5. If valid, create check-in record.
6. Return success result.

A participant must not be able to check themselves in
unless explicitly authorized.

Only organizer/admin or authorized event staff
can perform check-in.

Duplicate check-in must be prevented.

---

# CHECK-IN

Check-in should store:

registration_id
checked_in_by
checked_in_at

The system must prevent:

- Invalid registration
- Cancelled registration
- Duplicate check-in
- Unauthorized check-in

Check-in actions should be logged properly.

---

# REPORTING

Organizer dashboard should provide:

- Total events
- Published events
- Total registrations
- Total checked-in participants
- Ticket sales/count
- Registration statistics

Reports should be based on database queries,
not hardcoded numbers.

---

# UI / UX

Design style:

Modern
Professional
Clean
Premium
Minimal
Responsive

Use:

- Card-based layouts
- Soft shadows
- Rounded corners
- Clear typography
- Consistent spacing
- Responsive tables
- Responsive forms
- Empty states
- Loading states
- Success states
- Error states

Primary dashboard should use a modern sidebar layout.

The UI must work well on:

- Desktop
- Tablet
- Mobile

Do not create a desktop-only interface.

---

# DASHBOARD

Organizer dashboard should contain:

Stats:

Total Events
Total Participants
Total Tickets
Total Check-ins

Recent events.

Upcoming events.

Recent registrations.

Check-in statistics.

Use real database data.

Never use fake hardcoded statistics
after the relevant database functionality exists.

---

# SECURITY

Security is a priority.

Implement:

- Authentication
- Authorization
- CSRF protection
- Request validation
- Policies
- Role permissions
- Secure password handling
- Mass assignment protection
- Proper route protection

Never trust:

- URL parameters
- Form input
- Hidden form fields
- JavaScript validation

Backend validation is mandatory.

---

# TESTING

For every important feature create tests.

At minimum test:

Authentication
Authorization
Event creation
Event editing
Event deletion
Registration
Ticket quota
Duplicate registration
QR validation
Check-in
Duplicate check-in
Role permissions

Run tests after implementing important functionality.

If a test fails:

1. Read the error.
2. Identify the root cause.
3. Fix the implementation.
4. Run the test again.

Do not simply remove or weaken tests to make them pass.

---

# GIT

Work incrementally.

After completing a meaningful feature:

1. Run tests.
2. Check changed files.
3. Review the implementation.
4. Commit with a clear message.

Example:

feat: add event management

feat: add participant registration

feat: add qr check in

fix: prevent duplicate registration

Never commit:

.env

credentials

API keys

private secrets

---

# AI AGENT BEHAVIOR

Before modifying code:

1. Inspect the project structure.
2. Inspect relevant files.
3. Understand existing architecture.
4. Identify dependencies.
5. Determine the smallest safe change.

Do not blindly create files.

Do not duplicate existing functionality.

Do not change unrelated files.

If requirements are ambiguous:

- choose a sensible Laravel convention
- document the assumption
- continue implementation

Do not repeatedly ask unnecessary questions.

---

# IMPLEMENTATION ORDER

Build the system in this order:

PHASE 1
Project setup

PHASE 2
Authentication

PHASE 3
Database schema

PHASE 4
User roles and authorization

PHASE 5
Event management

PHASE 6
Ticket management

PHASE 7
Participant registration

PHASE 8
Digital ticket

PHASE 9
QR Code generation

PHASE 10
QR Check-in

PHASE 11
Organizer dashboard

PHASE 12
Reports

PHASE 13
Admin dashboard

PHASE 14
UI/UX polishing

PHASE 15
Testing

PHASE 16
Security review

PHASE 17
Production preparation

Never skip directly from Phase 1 to Phase 17.

---

# IMPORTANT AI RULE

DO NOT generate the entire project in one response.

Implement one phase at a time.

After each phase:

- verify files
- run relevant commands
- run tests
- fix errors
- explain what changed
- wait for the next instruction

The goal is a maintainable Laravel application,
not merely code that appears to work.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.2. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
