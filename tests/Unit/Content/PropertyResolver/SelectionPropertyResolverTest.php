<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Content\PropertyResolver;

use Manuxi\SuluGlossaryBundle\Content\PropertyResolver\GlossaryEntrySelectionPropertyResolver;
use Manuxi\SuluGlossaryBundle\Content\PropertyResolver\SingleGlossaryEntrySelectionPropertyResolver;
use Manuxi\SuluGlossaryBundle\Content\ResourceLoader\GlossaryEntryResourceLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;

class SelectionPropertyResolverTest extends TestCase
{
    public function testTypes(): void
    {
        $this->assertSame('glossary_entry_selection', GlossaryEntrySelectionPropertyResolver::getType());
        $this->assertSame('single_glossary_entry_selection', SingleGlossaryEntrySelectionPropertyResolver::getType());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function emptyMultiSelectionProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty array' => [[]];
        yield 'string' => ['uuid'];
        yield 'associative array' => [['a' => 'uuid']];
    }

    #[DataProvider('emptyMultiSelectionProvider')]
    public function testMultiSelectionResolvesEmptyForInvalidData(mixed $data): void
    {
        $view = (new GlossaryEntrySelectionPropertyResolver())->resolve($data, 'en');

        $this->assertSame([], $view->getContent());
        $this->assertSame(['ids' => []], $view->getView());
    }

    public function testMultiSelectionCreatesOneResolvablePerId(): void
    {
        $view = (new GlossaryEntrySelectionPropertyResolver())->resolve(['uuid-2', 'uuid-1'], 'en');

        $content = $view->getContent();
        $this->assertIsArray($content);
        $this->assertCount(2, $content);
        $this->assertContainsOnlyInstancesOf(ResolvableResource::class, $content);
        $this->assertSame('uuid-2', $content[0]->getId());
        $this->assertSame('uuid-1', $content[1]->getId());
        $this->assertSame(GlossaryEntryResourceLoader::getKey(), $content[0]->getResourceLoaderKey());
    }

    public function testMultiSelectionAllowsAnotherResourceLoader(): void
    {
        $view = (new GlossaryEntrySelectionPropertyResolver())->resolve(['uuid'], 'en', ['resourceLoader' => 'custom']);

        $this->assertSame('custom', $view->getContent()[0]->getResourceLoaderKey());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function emptySingleSelectionProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'array' => [['uuid']];
        yield 'integer' => [5];
    }

    #[DataProvider('emptySingleSelectionProvider')]
    public function testSingleSelectionResolvesNullForInvalidData(mixed $data): void
    {
        $view = (new SingleGlossaryEntrySelectionPropertyResolver())->resolve($data, 'en');

        $this->assertNull($view->getContent());
        $this->assertSame(['id' => null], $view->getView());
    }

    public function testSingleSelectionCreatesAResolvable(): void
    {
        $view = (new SingleGlossaryEntrySelectionPropertyResolver())->resolve('uuid-1', 'en');

        $content = $view->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $content);
        $this->assertSame('uuid-1', $content->getId());
        $this->assertSame(GlossaryEntryResourceLoader::getKey(), $content->getResourceLoaderKey());
        $this->assertSame('uuid-1', $view->getView()['id']);
    }
}
