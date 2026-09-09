# Contributing to gowa-php/laravel

Thank you for considering contributing to `gowa-php/laravel`! We welcome contributions, bug reports, and feature suggestions from the community.

## Code of Conduct

Please review and adhere to our [Code of Conduct](CODE_OF_CONDUCT.md) in all project interactions.

## Prerequisites & Framework Compatibility

This package is designed for broad compatibility:
- **PHP**: `>= 8.2` (supporting PHP 8.2, 8.3, and 8.4)
- **Laravel**: `^10.0 | ^11.0 | ^12.0 | ^13.0`
- **Testing Engine**: Pest PHP 3 with `orchestra/testbench`

## Development Setup

1. **Fork and clone** the repository:
   ```bash
   git clone https://github.com/Gowa-PHP/laravel.git
   cd laravel
   ```

2. **Install dependencies** via Composer:
   ```bash
   composer install
   ```

3. **Run the test suite** using Pest PHP:
   ```bash
   composer test
   # or test with specific database driver
   composer test:sqlite
   composer test:mysql
   composer test:pgsql
   ```

4. **Run code style checks**:
   ```bash
   composer run lint
   # or fix formatting automatically
   composer run lint:fix
   ```

## Development Guidelines

1. **Branch Naming**:
   - Features: `feat/feature-name`
   - Bug fixes: `fix/issue-description`
   - Chores/Docs: `chore/task-name` or `docs/update-description`

2. **Testing**:
   - Write Pest tests using `test('describes behavior', function () { ... })`.
   - Put unit tests in `tests/Unit` and integration / feature tests in `tests/Feature`.
   - Use `RefreshDatabase` when database persistence is involved.
   - Run `composer test` to verify all tests pass before submitting.

3. **Coding Standards**:
   - Follow strict types in every PHP file (`declare(strict_types=1);`).
   - Follow PSR-12 and the project's PHP-CS-Fixer configuration.
   - Run `composer run lint:fix` to automatically format your code.

4. **Submitting a Pull Request**:
   - Open a Pull Request targeting the `main` branch.
   - Use Conventional Commit prefixes (e.g. `feat: ...`, `fix: ...`, `docs: ...`).
   - Describe your changes, motivation, and verification steps in the PR description.

## Security Vulnerabilities

If you discover a security vulnerability, please consult our [Security Policy](SECURITY.md) instead of filing a public issue.
