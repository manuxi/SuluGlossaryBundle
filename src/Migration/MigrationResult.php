<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Migration;

class MigrationResult
{
    public int $entries = 0;

    public int $skipped = 0;

    public int $translations = 0;

    public int $published = 0;

    public int $versions = 0;

    public int $routes = 0;

    public bool $settings = false;

    /**
     * @var array<string, string>
     */
    public array $errors = [];

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}
