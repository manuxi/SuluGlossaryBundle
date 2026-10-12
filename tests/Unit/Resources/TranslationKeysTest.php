<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Resources;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

class TranslationKeysTest extends TestCase
{
    private const BASE = __DIR__ . '/../../../src/Resources';

    /**
     * @return iterable<string, array{string}>
     */
    public static function localeProvider(): iterable
    {
        yield 'en' => ['en'];
        yield 'de' => ['de'];
    }

    #[DataProvider('localeProvider')]
    public function testAdminTranslationsContainAllKeysUsedInConfiguration(string $locale): void
    {
        $translations = $this->flatten(Yaml::parseFile(self::BASE . '/translations/admin.' . $locale . '.yaml'));

        $missing = [];
        foreach ($this->usedKeys() as $key) {
            if (!\array_key_exists($key, $translations)) {
                $missing[] = $key;
            }
        }

        $this->assertSame([], $missing, 'Missing keys in admin.' . $locale . '.yaml');
    }

    public function testGermanAndEnglishTranslationsHaveTheSameKeys(): void
    {
        foreach (['admin', 'messages'] as $domain) {
            $english = array_keys($this->flatten(Yaml::parseFile(self::BASE . '/translations/' . $domain . '.en.yaml')));
            $german = array_keys($this->flatten(Yaml::parseFile(self::BASE . '/translations/' . $domain . '.de.yaml')));

            $this->assertEqualsCanonicalizing($english, $german, $domain);
        }
    }

    /**
     * Keys which are used inside XML configuration (templates, lists, forms) and PHP translation calls.
     *
     * @return string[]
     */
    private function usedKeys(): array
    {
        $keys = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::BASE . '/config', FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ('xml' !== $file->getExtension()) {
                continue;
            }

            preg_match_all('#>(sulu_glossary\.[a-z0-9_.]+)<|translation="(sulu_glossary\.[a-z0-9_.]+)"|value="(sulu_glossary\.[a-z0-9_.]+)"#', (string) file_get_contents($file->getPathname()), $matches);
            foreach (array_merge($matches[1], $matches[2], $matches[3]) as $key) {
                if ('' !== $key) {
                    $keys[$key] = true;
                }
            }
        }

        $keys['sulu_glossary.glossary'] = true;
        $keys['sulu_glossary.glossary_entries'] = true;
        $keys['sulu_glossary.glossary_entry_selection_label'] = true;
        $keys['sulu_glossary.select_glossary_entry'] = true;
        $keys['sulu_glossary.select_glossary_entries'] = true;
        $keys['sulu_glossary.no_glossary_entry_selected'] = true;
        $keys['sulu_glossary.config.tab'] = true;
        $keys['sulu_glossary.config.title.navi'] = true;

        return array_keys($keys);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
            if (\is_array($value)) {
                $result += $this->flatten($value, $path);
            } else {
                $result[$path] = (string) $value;
            }
        }

        return $result;
    }
}
