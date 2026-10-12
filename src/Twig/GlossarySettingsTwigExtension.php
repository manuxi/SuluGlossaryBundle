<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GlossarySettingsTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('load_glossary_settings', [$this, 'loadGlossarySettings']),
        ];
    }

    public function loadGlossarySettings(): GlossaryEntrySettings
    {
        $settings = $this->entityManager->getRepository(GlossaryEntrySettings::class)->findOneBy([]);

        return $settings instanceof GlossaryEntrySettings ? $settings : new GlossaryEntrySettings();
    }
}
