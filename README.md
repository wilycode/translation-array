
# WilyCode Translation Array

[![Tests](https://github.com/wilycode/translation-array/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/translation-array/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/wilycode/translation-array.svg)](https://packagist.org/packages/wilycode/translation-array)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A lightweight, **lazy-loading PHP array translation provider** with language fallback, pluralization, nested keys, and resource mounts. Implements `WilyCode\Translation\TranslatorInterface` without requiring a framework or a database.

> ### 🧩 Part of the [WilyCode PHP Ecosystem](https://github.com/wilycode/wilycode)
>
> WilyCode provides independent PHP packages with their own repositories, releases, and Composer installation. **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)** for related libraries, documentation, and integrations.

---

## Contents

- [Why Translation Array?](#why-translation-array)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Translation File Structure](#translation-file-structure)
- [Nested Keys and Missing Messages](#nested-keys-and-missing-messages)
- [Pluralization and Fallbacks](#pluralization-and-fallbacks)
- [Resource Mounts](#resource-mounts)
- [How Loading Works](#how-loading-works)
- [API Reference](#api-reference)
- [Limitations and Security](#limitations-and-security)
- [Development and Testing](#development-and-testing)
- [Related Packages](#related-packages)
- [License](#license)

## Why Translation Array?

Store translated messages in regular PHP files, organized by language and resource. Each resource is loaded when first requested, then cached **within the translator instance**.

| Capability | Behavior |
| --- | --- |
| Plain PHP files | Each translation resource returns an array |
| Lazy loading | Only requested resources are loaded |
| Language fallback | Missing messages can use a second language |
| Plural forms | Uses the appropriate language's pluralization rules |
| Dot notation | Supports nested arrays and literal dotted keys |
| Resource mounts | Route separate translation prefixes to their own directories |
| Framework independence | Implements a small shared translation contract |

This package is a **translation provider**. The language rule implementations and locale-specific date/number formatting live in separate packages.

## Requirements

- PHP **8.4** or newer.
- [`wilycode/translation`](https://github.com/wilycode/translation) **`^0.1.1`** (installed automatically).
- Composer 2 for normal package installation.

## Installation

```bash
composer require wilycode/translation-array:^0.1
```

For the ready-made `Pl` and `En` language classes shown below, also install:

```bash
composer require wilycode/translation-languages:^0.2
```

**Only the core `translation` package is required in production.** The `translation-languages` package is optional: use your own `LanguageInterface` implementation if your application should not download the complete set of languages. See [Translation Languages](https://github.com/wilycode/translation-languages) for selective language installation.

## Quick Start

### 1. Create message files

```text
project/
└── translations/
    ├── en/
    │   └── app/
    │       └── messages.php
    └── pl/
        └── app/
            └── messages.php
```

`translations/en/app/messages.php`:

```php
<?php

declare(strict_types=1);

return [
    'welcome' => 'Welcome, {name}!',
    'items' => ['{count} item', '{count} items'],
];
```

`translations/pl/app/messages.php`:

```php
<?php

declare(strict_types=1);

return [
    'welcome' => 'Witaj, {name}!',
    'items' => [
        '{count} element',
        '{count} elementy',
        '{count} elementów',
    ],
];
```

### 2. Create the translator

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use WilyCode\Translation\Language\En;
use WilyCode\Translation\Language\Pl;
use WilyCode\TranslationArray\ArrayTranslator;

$translator = new ArrayTranslator(
    directory: __DIR__ . '/translations',
    language: new Pl(),
    fallbackLanguage: new En(),
);

echo $translator->translate('app.messages', 'welcome', ['name' => 'Ada']);
// Witaj, Ada!

echo $translator->translatePlural('app.messages', 'items', 5);
// 5 elementów
```

`ArrayTranslator` implements `TranslatorInterface`, so it can be injected anywhere the shared interface is expected.

## Translation File Structure

A resource identifier uses dots to describe the relative filename:

```text
app.messages          → <directory>/<language>/app/messages.php
article.list          → <directory>/<language>/article/list.php
admin.auth.errors     → <directory>/<language>/admin/auth/errors.php
```

The last path element is a PHP file that **must return an array**. Identifiers may contain letters, digits, underscores, hyphens, and dots. Missing resource files are treated as empty arrays.

For example:

```php
$translator->translate('article.list', 'heading');
```

with the primary language `pl` loads `translations/pl/article/list.php` when that resource is first used.

## Nested Keys and Missing Messages

A message file can contain both flat and nested keys:

```php
return [
    'validation.required' => 'This field is required.',
    'profile' => [
        'title' => 'Your profile',
    ],
];
```

```php
$translator->translate('app.messages', 'validation.required');
// This field is required.

$translator->translate('app.messages', 'profile.title');
// Your profile
```

If the same key exists both **literally** (`'profile.title'`) and as nested keys (`['profile' => ['title' => ...]]`), the literal key takes precedence.

When a message is missing from both languages, the return value is `resource.key`, such as `app.messages.unknown`. An array is not a valid singular message value.

## Pluralization and Fallbacks

Use `translatePlural()` to select a plural form using the active language's `getPluralForm($count)` method:

```php
$translator->translatePlural('app.messages', 'items', 1); // 1 element
$translator->translatePlural('app.messages', 'items', 2); // 2 elementy
$translator->translatePlural('app.messages', 'items', 5); // 5 elementów
```

The array indices correspond to the language's plural categories. For example, Polish uses **three** forms and English uses **two**. The translator automatically sets `{count}` to the requested count, overriding any `count` value supplied in the parameters array.

If the primary language lacks a plural entry, the provider looks in the fallback language and uses **that fallback language's pluralization rule**. A missing or empty plural entry in both languages returns `resource.key`.

If the requested plural form is not available in a non-empty array, the provider uses the first available form. Keep all forms complete and correctly indexed to avoid an unexpected fallback.

For country-specific pluralization, use the appropriate `LanguageInterface` implementation from [`translation-languages`](https://github.com/wilycode/translation-languages) or your own implementation.

## Resource Mounts

Resource mounts let you keep translations in modules, themes, or plugins without moving them to the application's main translation directory.

```php
$translator = new ArrayTranslator(
    directory: __DIR__ . '/translations',
    language: new Pl(),
    fallbackLanguage: new En(),
    resourceMounts: [
        'theme.default' => __DIR__ . '/themes/default/languages',
    ],
);

$translator->translate('theme.default.entry.article', 'label');
```

The example reads the primary file at:

```text
themes/default/languages/pl/entry/article.php
```

and, if needed, the fallback file at `themes/default/languages/en/entry/article.php`.

| Resource | Resolved path within mount |
| --- | --- |
| `theme.default.entry.article` | `<mount>/<language>/entry/article.php` |
| `theme.default` | `<mount>/<language>/messages.php` |
| `app.messages` | `<main>/<language>/app/messages.php` (unmounted) |

The **longest matching resource prefix wins**. Mount prefixes must match an entire resource segment: `theme.default` does not match `theme.defaultx`.

## How Loading Works

1. The first call for a resource loads its primary language's PHP array file.
2. If the fallback language differs, its resource file is also loaded at that time.
3. Both arrays are cached in that `ArrayTranslator` instance for subsequent lookups.
4. New translator instances start with an empty cache. There is no cross-request or external cache.

The array provider is **read-only**. To pick up edits made to resource files, create a new translator instance. It does not provide a file watcher or cache invalidation API.

## API Reference

### `ArrayTranslator`

```php
$translator = new ArrayTranslator(
    directory: __DIR__ . '/translations',
    language: $language,
    fallbackLanguage: $fallbackLanguage,
    resourceMounts: ['theme.default' => __DIR__ . '/theme/languages'],
);
```

The constructor accepts `string $directory`, `LanguageInterface $language`, an optional `?LanguageInterface $fallbackLanguage`, and an optional `array $resourceMounts`.

| Parameter | Description |
| --- | --- |
| `$directory` | Root directory for the default translation resources |
| `$language` | Active language and pluralization rules |
| `$fallbackLanguage` | Optional language used for missing messages |
| `$resourceMounts` | Map of resource prefixes to other translation directories |

### Public methods

| Method | Return type | Behavior |
| --- | --- | --- |
| `translate(string $resource, string $key, array $parameters = [])` | `string` | Look up a scalar message and replace placeholders |
| `translatePlural(string $resource, string $key, int $count, array $parameters = [])` | `string` | Select a plural message and inject `{count}` |

Parameters use the same string placeholder semantics as [`MessageFormatter`](https://github.com/wilycode/translation). For example, `['name' => 'Ada']` substitutes `{name}`. The provider does not perform ICU message parsing or HTML escaping.

## Limitations and Security

- **Trust translation files.** PHP `require` executes file contents. Do not load untrusted user-uploaded files or arbitrary paths.
- **Validate paths.** Resources, keys, mount prefixes, and language codes are validated against safe identifier characters; mount *directory paths* are supplied by the application and should be trusted.
- **Escape on output.** Placeholder replacement does not escape HTML. Apply context-appropriate escaping when rendering translated text in HTML or other output formats.
- **Plural array values are trusted data.** Store non-empty arrays of string messages in the order expected by the language rule. This package does not validate the linguistic correctness of translation resources.
- **No locale date/number formatter.** Regional data belongs to `translation-languages`; generic formatting implementations belong to `translation`.
- **No built-in persistence.** The provider reads PHP array resources; it does not edit them or access a database.

## Development and Testing

```bash
composer validate --strict
composer install
composer test
```

Tests run on PHP 8.4 via [GitHub Actions](https://github.com/wilycode/translation-array/actions). The test suite uses `wilycode/translation-languages` as a **development-only dependency** for real Polish and English pluralization.

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines and [CHANGELOG.md](CHANGELOG.md) for version history.

## Related Packages

| Package | Purpose |
| --- | --- |
| [`wilycode/translation`](https://github.com/wilycode/translation) | Shared translation contract, message and locale formatting |
| [`wilycode/translation-languages`](https://github.com/wilycode/translation-languages) | Language pluralization rules and regional configuration resources |
| [`wilycode/translation-array`](https://github.com/wilycode/translation-array) | PHP array translation provider (this package) |
| [`wilycode/translation-database`](https://github.com/wilycode/translation-database) | Database-backed translation provider |

## License

MIT. See [LICENSE](LICENSE).
