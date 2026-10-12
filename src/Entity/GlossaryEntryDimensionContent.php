<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Entity;

use DateTimeImmutable;
use Sulu\Content\Domain\Model\AuditableInterface;
use Sulu\Content\Domain\Model\AuditableTrait;
use Sulu\Content\Domain\Model\AuthorInterface;
use Sulu\Content\Domain\Model\AuthorTrait;
use Sulu\Content\Domain\Model\ContentRichEntityInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\DimensionContentTrait;
use Sulu\Content\Domain\Model\ExcerptInterface;
use Sulu\Content\Domain\Model\ExcerptTrait;
use Sulu\Content\Domain\Model\RoutableInterface;
use Sulu\Content\Domain\Model\RoutableTrait;
use Sulu\Content\Domain\Model\SeoInterface;
use Sulu\Content\Domain\Model\SeoTrait;
use Sulu\Content\Domain\Model\TaxonomyInterface;
use Sulu\Content\Domain\Model\TaxonomyTrait;
use Sulu\Content\Domain\Model\TemplateInterface;
use Sulu\Content\Domain\Model\TemplateTrait;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Content\Domain\Model\WorkflowTrait;

/**
 * @implements DimensionContentInterface<GlossaryEntry>
 */
class GlossaryEntryDimensionContent implements DimensionContentInterface, TemplateInterface, RoutableInterface, SeoInterface, ExcerptInterface, TaxonomyInterface, AuthorInterface, WorkflowInterface, AuditableInterface
{
    use AuditableTrait;
    use AuthorTrait;
    use DimensionContentTrait;
    use ExcerptTrait;
    use RoutableTrait;
    use SeoTrait;
    use TaxonomyTrait;
    use TemplateTrait {
        TemplateTrait::setTemplateData as parentSetTemplateData;
    }
    use WorkflowTrait;

    public const TYPE_ABBREVIATION = 'abbreviation';
    public const TYPE_ACRONYM = 'acronym';
    public const TYPE_TERM = 'term';
    public const TYPES = [self::TYPE_ABBREVIATION, self::TYPE_ACRONYM, self::TYPE_TERM];
    public const DEFAULT_TYPE = self::TYPE_TERM;

    protected ?int $id = null;

    protected GlossaryEntry $glossaryEntry;

    protected ?string $title = null;

    protected ?string $explanation = null;

    protected ?string $type = self::DEFAULT_TYPE;

    public function __construct(GlossaryEntry $glossaryEntry)
    {
        $this->glossaryEntry = $glossaryEntry;
        $this->created = new DateTimeImmutable();
        $this->changed = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResource(): ContentRichEntityInterface
    {
        return $this->glossaryEntry;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = null !== $type && \in_array($type, self::TYPES, true) ? $type : self::DEFAULT_TYPE;

        return $this;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    public function setTemplateData(array $templateData): void
    {
        if (\array_key_exists('title', $templateData)) {
            $this->title = \is_string($templateData['title']) ? $templateData['title'] : null;
        }

        if (\array_key_exists('explanation', $templateData)) {
            $this->explanation = \is_string($templateData['explanation']) ? $templateData['explanation'] : null;
        }

        $this->parentSetTemplateData($templateData);
    }

    public static function getTemplateType(): string
    {
        return GlossaryEntry::TEMPLATE_TYPE;
    }

    public static function getResourceKey(): string
    {
        return GlossaryEntry::RESOURCE_KEY;
    }
}
