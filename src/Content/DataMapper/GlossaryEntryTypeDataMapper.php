<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Content\DataMapper;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * Stores the unlocalized type on the unlocalized and the localized dimension content,
 * so lists and filters can read it from the localized row.
 */
class GlossaryEntryTypeDataMapper implements DataMapperInterface
{
    public function map(
        DimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!$unlocalizedDimensionContent instanceof GlossaryEntryDimensionContent
            || !$localizedDimensionContent instanceof GlossaryEntryDimensionContent
            || !\array_key_exists('type', $data)
        ) {
            return;
        }

        $type = \is_string($data['type']) ? $data['type'] : null;

        $unlocalizedDimensionContent->setType($type);
        $localizedDimensionContent->setType($type);
    }
}
