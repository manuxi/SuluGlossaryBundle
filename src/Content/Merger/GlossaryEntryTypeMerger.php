<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Content\Merger;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Sulu\Content\Application\ContentMerger\Merger\MergerInterface;

class GlossaryEntryTypeMerger implements MergerInterface
{
    public function merge(object $targetObject, object $sourceObject): void
    {
        if (!$targetObject instanceof GlossaryEntryDimensionContent
            || !$sourceObject instanceof GlossaryEntryDimensionContent
        ) {
            return;
        }

        if (null !== $sourceObject->getType()) {
            $targetObject->setType($sourceObject->getType());
        }
    }
}
