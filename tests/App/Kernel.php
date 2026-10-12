<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\App;

use Manuxi\SuluGlossaryBundle\SuluGlossaryBundle;
use ReflectionClass;
use Sulu\Bundle\AudienceTargetingBundle\SuluAudienceTargetingBundle;
use Sulu\Bundle\TestBundle\Kernel\SuluTestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class Kernel extends SuluTestKernel
{
    public function registerBundles(): iterable
    {
        $bundles = [...parent::registerBundles()];
        $bundles[] = new SuluGlossaryBundle();

        foreach ($bundles as $key => $bundle) {
            if ($bundle instanceof SuluAudienceTargetingBundle) {
                unset($bundles[$key]);
            }
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__ . '/config/config_' . $this->getContext() . '.yml');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        $gedmoReflection = new ReflectionClass(\Gedmo\Exception::class);
        $parameters['gedmo_directory'] = \dirname((string) $gedmoReflection->getFileName());

        return $parameters;
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }
}
