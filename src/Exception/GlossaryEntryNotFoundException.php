<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Exception;

use Exception;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Throwable;

class GlossaryEntryNotFoundException extends Exception
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(private readonly array $filters, int $code = 0, ?Throwable $previous = null)
    {
        $criteria = [];
        foreach ($filters as $key => $value) {
            $criteria[] = \sprintf('"%s" %s', $key, \is_object($value) ? get_debug_type($value) : json_encode($value));
        }

        parent::__construct(
            \sprintf('Model "%s" with %s not found', GlossaryEntry::class, implode(' and ', $criteria)),
            $code,
            $previous
        );
    }

    public function getModel(): string
    {
        return GlossaryEntry::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
