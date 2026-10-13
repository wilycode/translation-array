<?php

declare(strict_types=1);

namespace WilyCode\TranslationArray\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WilyCode\Translation\Language\En;
use WilyCode\Translation\Language\Pl;
use WilyCode\Translation\TranslatorInterface;
use WilyCode\TranslationArray\ArrayTranslator;

#[CoversClass(ArrayTranslator::class)]
final class ArrayTranslatorTest extends TestCase
{
    private const string TRANSLATIONS = __DIR__ . '/Fixtures/translations';
    private const string THEME = __DIR__ . '/Fixtures/theme-translations';

    public function test_implements_translation_contract(): void
    {
        self::assertInstanceOf(TranslatorInterface::class, new ArrayTranslator(self::TRANSLATIONS, new En()));
    }

    public function test_translates_primary_language_with_parameters(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new Pl(), new En());
        self::assertSame('Cześć Simon', $translator->translate('app.messages', 'hello', ['name' => 'Simon']));
    }

    public function test_direct_dotted_key_takes_precedence_over_nested_key(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new Pl());
        self::assertSame('To pole jest wymagane.', $translator->translate('app.messages', 'validation.not_empty.required'));
        self::assertSame('Płaska etykieta', $translator->translate('app.messages', 'menu.title'));
        self::assertSame('Zagnieżdżona wartość', $translator->translate('app.messages', 'menu.subtitle'));
    }

    public function test_missing_key_uses_fallback_language(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new Pl(), new En());
        self::assertSame('Only EN', $translator->translate('app.messages', 'only_en'));
    }

    public function test_missing_key_returns_resource_and_key(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new En());
        self::assertSame('app.messages.unknown', $translator->translate('app.messages', 'unknown'));
        self::assertSame('app.unknown.hello', $translator->translate('app.unknown', 'hello'));
    }

    public function test_singular_non_scalar_value_is_not_returned_as_a_message(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new Pl());
        self::assertSame('app.messages.items', $translator->translate('app.messages', 'items'));
    }

    public function test_plural_forms_follow_polish_language_rules(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new Pl(), new En());
        self::assertSame('1 element', $translator->translatePlural('app.messages', 'items', 1));
        self::assertSame('2 elementy', $translator->translatePlural('app.messages', 'items', 2));
        self::assertSame('5 elementów', $translator->translatePlural('app.messages', 'items', 5));
        self::assertSame('22 elementy', $translator->translatePlural('app.messages', 'items', 22));
    }

    public function test_fallback_plural_uses_fallback_language_rules(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new Pl(), new En());
        self::assertSame('1 file', $translator->translatePlural('app.messages', 'files_only_en', 1));
        self::assertSame('2 files', $translator->translatePlural('app.messages', 'files_only_en', 2));
        self::assertSame('5 files', $translator->translatePlural('app.messages', 'files_only_en', 5));
    }

    public function test_plural_injects_count_and_overrides_the_caller_count(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new En());
        self::assertSame('2 items', $translator->translatePlural('app.messages', 'items', 2, ['count' => 77]));
    }

    public function test_missing_plural_returns_resource_and_key(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new En());
        self::assertSame('app.messages.unknown', $translator->translatePlural('app.messages', 'unknown', 2));
    }

    public function test_mounted_resources_support_translation_and_fallback(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new Pl(), new En(), [
            'theme.default' => self::THEME,
        ]);
        self::assertSame('Artykuły motywu', $translator->translate('theme.default.entry.article', 'label'));
        self::assertSame('Theme fallback', $translator->translate('theme.default.entry.article', 'fallback_only'));
        self::assertSame('Cześć Ada', $translator->translate('app.messages', 'hello', ['name' => 'Ada']));
    }

    public function test_mounted_resource_exact_prefix_maps_to_messages_file(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new En(), null, [
            'theme.default' => self::THEME,
        ]);
        self::assertSame('Theme home', $translator->translate('theme.default', 'heading'));
    }

    public function test_longest_matching_mount_is_used(): void
    {
        $translator = new ArrayTranslator(self::TRANSLATIONS, new En(), null, [
            'theme' => self::TRANSLATIONS,
            'theme.default' => self::THEME,
        ]);
        self::assertSame('Theme articles', $translator->translate('theme.default.entry.article', 'label'));
    }

    public function test_resource_is_cached_only_within_the_translator_instance(): void
    {
        $root = sys_get_temp_dir() . '/wilycode-array-' . bin2hex(random_bytes(8));
        $folder = $root . '/en/app';
        self::assertSame(true, mkdir($folder, 0700, true));
        $path = $folder . '/messages.php';

        try {
            file_put_contents($path, "<?php return ['value' => 'Original'];");
            $translator = new ArrayTranslator($root, new En());
            self::assertSame('Original', $translator->translate('app.messages', 'value'));

            file_put_contents($path, "<?php return ['value' => 'Updated'];");
            self::assertSame('Original', $translator->translate('app.messages', 'value'));
            self::assertSame('Updated', (new ArrayTranslator($root, new En()))->translate('app.messages', 'value'));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            rmdir($folder);
            rmdir(dirname($folder));
            rmdir($root);
        }
    }

    public function test_invalid_empty_directory_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArrayTranslator('   ', new En());
    }

    public function test_invalid_empty_mount_directory_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArrayTranslator(self::TRANSLATIONS, new En(), null, ['theme' => '  ']);
    }

    public function test_invalid_mount_prefix_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArrayTranslator(self::TRANSLATIONS, new En(), null, ['../other' => self::THEME]);
    }

    public function test_invalid_resource_identifier_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArrayTranslator(self::TRANSLATIONS, new En()))->translate('../secret', 'hello');
    }

    public function test_invalid_translation_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArrayTranslator(self::TRANSLATIONS, new En()))->translate('app.messages', '../hello');
    }

    public function test_invalid_plural_translation_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArrayTranslator(self::TRANSLATIONS, new En()))->translatePlural('app.messages', '../items', 2);
    }

    public function test_invalid_translation_file_result_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArrayTranslator(self::TRANSLATIONS, new En()))->translate('app.invalid', 'test');
    }
}
