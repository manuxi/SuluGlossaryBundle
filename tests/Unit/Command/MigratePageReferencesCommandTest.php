<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Command;

use Manuxi\SuluGlossaryBundle\Command\MigratePageReferencesCommand;
use Manuxi\SuluGlossaryBundle\Migration\ProviderAliasMigrator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MigratePageReferencesCommandTest extends TestCase
{
    private ProviderAliasMigrator&MockObject $migrator;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->migrator = $this->createMock(ProviderAliasMigrator::class);
        $this->tester = new CommandTester(new MigratePageReferencesCommand($this->migrator));
    }

    public function testListsTheChangedRows(): void
    {
        $this->migrator->expects($this->once())->method('migrate')->with(false)->willReturn(['pa_page_dimension_contents' => 3]);

        $this->assertSame(0, $this->tester->execute([]));
        $this->assertStringContainsString('pa_page_dimension_contents', $this->tester->getDisplay());
        $this->assertStringContainsString('Rows changed', $this->tester->getDisplay());
    }

    public function testDryRunIsPassedToTheMigrator(): void
    {
        $this->migrator->expects($this->once())->method('migrate')->with(true)->willReturn(['sn_snippet_dimension_contents' => 1]);

        $this->assertSame(0, $this->tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('Rows to change', $this->tester->getDisplay());
    }

    public function testWarnsWhenNoContentTableExists(): void
    {
        $this->migrator->method('migrate')->willReturn([]);

        $this->assertSame(0, $this->tester->execute([]));
        $this->assertStringContainsString('None of the content tables', $this->tester->getDisplay());
    }
}
