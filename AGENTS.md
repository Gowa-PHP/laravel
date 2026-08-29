# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel package that integrates the GOWA WhatsApp API. Package code lives in `src/` under the `Gowa\\Laravel` namespace. Keep Eloquent models in `src/Models`, enums in `src/Enums`, notification code in `src/Notifications`, and webhook controllers and events in `src/Webhook`.

Package configuration is in `config/gowa.php`; publishable database migrations are in `database/migrations/`. Tests mirror package behavior in `tests/Unit` and `tests/Feature`, with shared Testbench setup in `tests/TestCase.php` and Pest configuration in `tests/Pest.php`. Documentation and visual assets are in `README.md`, `README.pt.md`, and `art/`.

## Build, Test, and Development Commands

- `composer install` installs the PHP and development dependencies.
- `composer test` runs the Pest suite (equivalent to `vendor/bin/pest`).
- `vendor/bin/pest tests/Unit/GowaInstanceTest.php` runs one test file while iterating.

The package targets PHP 8.2+ and Laravel 10–12. Tests run through Orchestra Testbench, so do not require a separate Laravel application.

## Coding Style & Naming Conventions

Follow the existing PHP style: strict types in every PHP file, PSR-4 namespaces, four-space indentation, trailing commas in multiline argument and array lists, and typed method signatures. Use PascalCase for classes and enums (`GowaMessageStatus`), camelCase for methods and variables (`verifyWebhookSignature`), and descriptive migration names such as `create_gowa_instances_table`.

There is no repository formatter configuration. Match nearby code and keep imports alphabetized where practical. Place new package classes under the matching `Gowa\\Laravel\\...` namespace and update Composer autoloading only for new top-level source roots.

## Testing Guidelines

Write Pest tests using `test('describes behavior', function () { ... })`. Put isolated model, enum, and service behavior in `tests/Unit`; exercise routes, events, database integration, and Laravel bindings in `tests/Feature`. Use `RefreshDatabase` where persistence is involved. Run `composer test` before opening a pull request.

## Commit & Pull Request Guidelines

Recent commits use Conventional Commit prefixes, for example `feat: initial gowa-laravel package structure` and `fix(tests): resolve route collision`. Use concise imperative subjects such as `fix(webhook): validate missing signature`.

Keep pull requests focused. Include a clear summary, relevant test results, linked issues when applicable, and documentation updates for public API or configuration changes. Add screenshots only for documentation or user-visible visual changes.

## Security & Configuration

Never commit GOWA credentials, webhook secrets, or real phone numbers. Use environment variables documented in the README, and ensure webhook signature verification remains covered by tests whenever that code changes.
