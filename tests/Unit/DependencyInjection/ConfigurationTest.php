<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\DependencyInjection;

use Manuxi\SuluGlossaryBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }

    public function testDefaults(): void
    {
        $config = $this->process([]);

        $this->assertSame(20, $config['versioning']['max_versions']);
        $this->assertFalse($config['twig']['auto_abbr']);
    }

    public function testOverrides(): void
    {
        $config = $this->process([['versioning' => ['max_versions' => 5], 'twig' => ['auto_abbr' => true]]]);

        $this->assertSame(5, $config['versioning']['max_versions']);
        $this->assertTrue($config['twig']['auto_abbr']);
    }

    public function testNegativeMaxVersionsAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['versioning' => ['max_versions' => -1]]]);
    }
}
