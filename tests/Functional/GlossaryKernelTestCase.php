<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Sulu\Bundle\TestBundle\Testing\KernelTestCase;

abstract class GlossaryKernelTestCase extends KernelTestCase
{
    private static bool $schemaCreated = false;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = $this->getEntityManager();

        if (!self::$schemaCreated) {
            $schemaTool = new SchemaTool($entityManager);
            $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
            $schemaTool->dropDatabase();
            $schemaTool->createSchema($metadata);
            self::$schemaCreated = true;
        }

        $entityManager->clear();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}
