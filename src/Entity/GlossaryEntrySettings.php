<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Entity;

use Sulu\Component\Persistence\Model\AuditableInterface;
use Sulu\Component\Persistence\Model\AuditableTrait;

class GlossaryEntrySettings implements AuditableInterface
{
    use AuditableTrait;

    public const RESOURCE_KEY = 'glossary_settings';
    public const FORM_KEY = 'glossary_config';
    public const SECURITY_CONTEXT = 'sulu.glossary.settings';

    private ?int $id = null;

    private ?bool $toggleHeader = null;

    private ?bool $toggleHero = null;

    private ?bool $toggleBreadcrumbs = null;

    private ?string $pageGlossary = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToggleHeader(): ?bool
    {
        return $this->toggleHeader;
    }

    public function setToggleHeader(?bool $toggleHeader): self
    {
        $this->toggleHeader = $toggleHeader;

        return $this;
    }

    public function getToggleHero(): ?bool
    {
        return $this->toggleHero;
    }

    public function setToggleHero(?bool $toggleHero): self
    {
        $this->toggleHero = $toggleHero;

        return $this;
    }

    public function getToggleBreadcrumbs(): ?bool
    {
        return $this->toggleBreadcrumbs;
    }

    public function setToggleBreadcrumbs(?bool $toggleBreadcrumbs): self
    {
        $this->toggleBreadcrumbs = $toggleBreadcrumbs;

        return $this;
    }

    public function getPageGlossary(): ?string
    {
        return $this->pageGlossary;
    }

    public function setPageGlossary(?string $pageGlossary): self
    {
        $this->pageGlossary = $pageGlossary;

        return $this;
    }
}
