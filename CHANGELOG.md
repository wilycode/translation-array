# Changelog

All notable changes to this package are documented here.

## [0.1.0] - Unreleased

### Added

- `ArrayTranslator` implementing `WilyCode\Translation\TranslatorInterface`.
- Lazy translation loading from PHP array resource files, including nested and direct dotted keys.
- Primary and fallback language support for singular messages and plural forms.
- Language-aware pluralization using `LanguageInterface`.
- Resource mounts with longest matching prefix taking priority.
- PHPUnit coverage, GitHub Actions, and complete usage documentation.

### Package naming

The pre-release `translate-array` sources were renamed to `wilycode/translation-array`, with namespace `WilyCode\TranslationArray` and class `ArrayTranslator`. The previous unpublished API is not maintained as an alias.

[0.1.0]: https://github.com/wilycode/translation-array/releases/tag/v0.1.0
