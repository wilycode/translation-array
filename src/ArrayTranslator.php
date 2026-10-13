<?php

declare(strict_types=1);

namespace WilyCode\TranslationArray;

use InvalidArgumentException;
use WilyCode\Translation\LanguageInterface;
use WilyCode\Translation\MessageFormatter;
use WilyCode\Translation\TranslatorInterface;

/**
 * Lazy translation provider backed by PHP array files.
 *
 * Resource `article.list` maps to `<directory>/<language>/article/list.php`.
 * Keys use dot notation and may traverse nested arrays.
 */
final class ArrayTranslator implements TranslatorInterface
{
    private readonly string $directory;

    /** @var array<string, string> */
    private readonly array $resourceMounts;

    /** @var array<string, array<string, mixed>> */
    private array $primary = [];

    /** @var array<string, array<string, mixed>> */
    private array $fallback = [];

    /** @var array<string, true> */
    private array $loaded = [];

    /**
     * @param array<string, string> $resourceMounts Resource-prefix => translation directory.
     *        Example: `theme.estateflow` => `/app/template/theme/estateflow/languages`.
     */
    public function __construct(
        string $directory,
        private readonly LanguageInterface $language,
        private readonly ?LanguageInterface $fallbackLanguage = null,
        array $resourceMounts = [],
    ) {
        $directory = rtrim($directory, " \t\n\r\0\x0B/\\");
        if ($directory === '') {
            throw new InvalidArgumentException('Translation directory cannot be empty.');
        }

        $this->directory = $directory;

        $normalizedMounts = [];
        foreach ($resourceMounts as $prefix => $mountDirectory) {
            $this->assertIdentifier($prefix, 'resource mount prefix');
            $mountDirectory = rtrim($mountDirectory, " \t\n\r\0\x0B/\\");
            if ($mountDirectory === '') {
                throw new InvalidArgumentException('Translation resource mount directory cannot be empty.');
            }
            $normalizedMounts[$prefix] = $mountDirectory;
        }

        uksort(
            $normalizedMounts,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left),
        );
        $this->resourceMounts = $normalizedMounts;
    }

    public function translate(string $resource, string $key, array $parameters = []): string
    {
        $this->ensureLoaded($resource);

        [$found, $value] = $this->find($this->primary[$resource], $key);
        if (!$found) {
            [$found, $value] = $this->find($this->fallback[$resource] ?? [], $key);
        }

        if (!$found || !is_scalar($value)) {
            return $resource . '.' . $key;
        }

        return MessageFormatter::format((string) $value, $parameters);
    }

    public function translatePlural(string $resource, string $key, int $count, array $parameters = []): string
    {
        $this->ensureLoaded($resource);
        $parameters['count'] = $count;

        [$found, $forms] = $this->find($this->primary[$resource], $key);
        $pluralLanguage = $this->language;

        if (!$found || !is_array($forms) || $forms === []) {
            [$found, $forms] = $this->find($this->fallback[$resource] ?? [], $key);
            $pluralLanguage = $this->fallbackLanguage ?? $this->language;
        }

        if (!$found || !is_array($forms) || $forms === []) {
            return $resource . '.' . $key;
        }

        $index = $pluralLanguage->getPluralForm($count);
        $message = $forms[$index] ?? reset($forms);

        return MessageFormatter::format((string) $message, $parameters);
    }

    private function ensureLoaded(string $resource): void
    {
        $this->assertIdentifier($resource, 'resource');
        if (isset($this->loaded[$resource])) {
            return;
        }

        $this->primary[$resource] = $this->loadFile($resource, $this->language->getCode());

        $fallbackLanguage = $this->fallbackLanguage;
        if ($fallbackLanguage !== null && $fallbackLanguage->getCode() !== $this->language->getCode()) {
            $this->fallback[$resource] = $this->loadFile($resource, $fallbackLanguage->getCode());
        }

        $this->loaded[$resource] = true;
    }

    /** @return array<string, mixed> */
    private function loadFile(string $resource, string $languageCode): array
    {
        $this->assertIdentifier($languageCode, 'language code');

        [$directory, $mountedResource] = $this->resolveResourceLocation($resource);
        $relativePath = str_replace('.', DIRECTORY_SEPARATOR, $mountedResource) . '.php';
        $path = $directory . DIRECTORY_SEPARATOR . $languageCode . DIRECTORY_SEPARATOR . $relativePath;

        if (!is_file($path)) {
            return [];
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new InvalidArgumentException('Translation file must return an array: ' . $path);
        }

        return $data;
    }


    /** @return array{0:string,1:string} */
    private function resolveResourceLocation(string $resource): array
    {
        foreach ($this->resourceMounts as $prefix => $directory) {
            if ($resource === $prefix) {
                return [$directory, 'messages'];
            }

            $needle = $prefix . '.';
            if (str_starts_with($resource, $needle)) {
                return [$directory, substr($resource, strlen($needle))];
            }
        }

        return [$this->directory, $resource];
    }

    /**
     * @param array<string, mixed> $messages
     * @return array{0:bool, 1:mixed}
     */
    private function find(array $messages, string $key): array
    {
        $this->assertIdentifier($key, 'translation key');

        if (array_key_exists($key, $messages)) {
            return [true, $messages[$key]];
        }

        $value = $messages;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }

        return [true, $value];
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid ' . $label . ': ' . $value);
        }
    }
}
