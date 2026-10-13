# Contributing to WilyCode Translation Array

Thanks for your interest in improving this library.

## Development

Requirements: PHP 8.4 or newer and Composer 2.

```bash
composer install
composer validate --strict
composer test
```

Please add or update PHPUnit tests for any change to translation behavior, fallback handling, resource mounts, or pluralization. Follow the existing formatting, keep public API changes intentional, and update [README.md](README.md) and [CHANGELOG.md](CHANGELOG.md) along with changes.

## Pull requests

Open a focused pull request against `main`, describing the issue, implementation, and testing performed.

## Security

Do not report security vulnerabilities in public issues. Use the repository's private vulnerability-reporting channel if enabled, or contact the maintainers privately.

See the [WilyCode PHP Ecosystem](https://github.com/wilycode/wilycode) for related packages.
