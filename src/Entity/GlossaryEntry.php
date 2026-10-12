<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Entity;

use Sulu\Component\Persistence\Model\AuditableInterface;
use Sulu\Component\Persistence\Model\AuditableTrait;
use Sulu\Content\Domain\Model\ContentRichEntityInterface;
use Sulu\Content\Domain\Model\ContentRichEntityTrait;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ContentRichEntityInterface<GlossaryEntryDimensionContent>
 */
class GlossaryEntry implements ContentRichEntityInterface, AuditableInterface
{
    use AuditableTrait;
    /**
     * @phpstan-use ContentRichEntityTrait<GlossaryEntryDimensionContent>
     */
    use ContentRichEntityTrait;

    public const RESOURCE_KEY = 'glossary_entries';
    public const LIST_KEY = 'glossary_entries';
    public const VERSIONS_RESOURCE_KEY = 'glossary_entries_versions';
    public const SECURITY_CONTEXT = 'sulu.glossary.entries';
    public const TEMPLATE_TYPE = 'glossary_entry';
    public const DEFAULT_TEMPLATE = 'default';

    protected string $uuid;

    public function __construct(?string $uuid = null)
    {
        $this->uuid = $uuid ?: Uuid::v7()->toRfc4122();
        $this->initializeDimensionContents();
    }

    public function getId(): string
    {
        return $this->uuid;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function createDimensionContent(): DimensionContentInterface
    {
        return new GlossaryEntryDimensionContent($this);
    }
}
