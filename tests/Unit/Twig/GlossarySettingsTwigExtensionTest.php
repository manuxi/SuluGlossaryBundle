<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use Manuxi\SuluGlossaryBundle\Twig\GlossarySettingsTwigExtension;
use PHPUnit\Framework\TestCase;

class GlossarySettingsTwigExtensionTest extends TestCase
{
    private function createExtension(?GlossaryEntrySettings $stored): GlossarySettingsTwigExtension
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($stored);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(GlossaryEntrySettings::class)->willReturn($repository);

        return new GlossarySettingsTwigExtension($entityManager);
    }

    public function testFunctionName(): void
    {
        $functions = $this->createExtension(null)->getFunctions();

        $this->assertSame('load_glossary_settings', $functions[0]->getName());
    }

    public function testReturnsTheStoredSettings(): void
    {
        $stored = (new GlossaryEntrySettings())->setToggleHero(true);

        $this->assertSame($stored, $this->createExtension($stored)->loadGlossarySettings());
    }

    public function testReturnsEmptySettingsWhenNothingIsStored(): void
    {
        $settings = $this->createExtension(null)->loadGlossarySettings();

        $this->assertNull($settings->getToggleHero());
    }
}
