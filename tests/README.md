# Testing

The test suite uses PHPUnit 11 and is configured in `phpunit.xml`.

## Setup

Install dependencies from the repository root:

```bash
composer install
```

## Running Tests

```bash
# Run all tests with the configured coverage reports
vendor/bin/phpunit

# Run all tests without generating coverage reports
./scripts/run-tests-fast.sh

# Run one suite
vendor/bin/phpunit --testsuite="Unit Tests"
vendor/bin/phpunit --testsuite="Integration Tests"
vendor/bin/phpunit --testsuite="End-to-End Tests"

# Run one file or matching tests
vendor/bin/phpunit tests/Unit/UserTest.php
vendor/bin/phpunit --filter GitHubUserTest
```

The end-to-end suite starts a local PHP HTTP server and therefore needs permission to bind to a local port.

## Coverage

Coverage includes application code in `entities/` and `pages/`. A PCOV or Xdebug coverage driver is required.

```bash
# Generate HTML, Clover XML, and terminal coverage reports
./scripts/run-coverage.sh
```

Generated reports are written to `coverage/`; open `coverage/index.html` for the HTML report.

## Layout

```text
tests/
|-- bootstrap.php              Shared test initialization
|-- Unit/                      Isolated domain and GitHub entity tests
|-- Integration/               Database and page workflow tests
|-- EndToEnd/                  HTTP-level application tests
`-- Mocks/                     Test doubles for GitHub and entities

phpunit.xml                    PHPUnit suites and coverage configuration
scripts/run-tests-fast.sh      Run tests without coverage reports
scripts/run-coverage.sh        Generate coverage reports
```

## Test Support

- `tests/Unit/Base/UnitTestCase.php` provides shared unit-test setup.
- `tests/Integration/Base/IntegrationTestCase.php` prepares integration fixtures and storage.
- `tests/Integration/Pages/PageTestCase.php` supports page-level workflow tests.
- `tests/EndToEnd/Base/WebTestCase.php` runs tests through a local web server.
- `tests/Mocks/` contains reusable test doubles.
