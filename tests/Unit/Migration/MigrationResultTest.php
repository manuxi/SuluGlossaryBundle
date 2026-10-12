<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Migration;

use Manuxi\SuluGlossaryBundle\Migration\MigrationResult;
use PHPUnit\Framework\TestCase;

class MigrationResultTest extends TestCase
{
    public function testStartsEmptyWithoutErrors(): void
    {
        $result = new MigrationResult();

        $this->assertSame(0, $result->entries);
        $this->assertFalse($result->settings);
        $this->assertFalse($result->hasErrors());
    }

    public function testReportsErrors(): void
    {
        $result = new MigrationResult();
        $result->errors['uuid'] = 'failed';

        $this->assertTrue($result->hasErrors());
    }
}
