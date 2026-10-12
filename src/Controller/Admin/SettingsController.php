<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluGlossaryBundle\Domain\Event\Settings\ModifiedEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/api')]
class SettingsController implements SecuredControllerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    #[Route('/glossary-settings', name: 'sulu_glossary.get_glossary_settings', methods: ['GET'])]
    public function getAction(): Response
    {
        $settings = $this->entityManager->getRepository(GlossaryEntrySettings::class)->findOneBy([]);

        return new JsonResponse($this->getDataForEntity($settings ?? new GlossaryEntrySettings()));
    }

    #[Route('/glossary-settings', name: 'sulu_glossary.put_glossary_settings', methods: ['PUT'])]
    public function putAction(Request $request): Response
    {
        $settings = $this->entityManager->getRepository(GlossaryEntrySettings::class)->findOneBy([]);
        if (!$settings instanceof GlossaryEntrySettings) {
            $settings = new GlossaryEntrySettings();
            $this->entityManager->persist($settings);
        }

        $data = $request->toArray();
        $this->mapDataToEntity($data, $settings);

        $this->domainEventCollector->collect(new ModifiedEvent($settings, $data));
        $this->entityManager->flush();

        return new JsonResponse($this->getDataForEntity($settings));
    }

    public function getSecurityContext(): string
    {
        return GlossaryEntrySettings::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        $locale = $request->query->get('locale');

        return \is_string($locale) ? $locale : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDataForEntity(GlossaryEntrySettings $settings): array
    {
        return [
            'toggleHeader' => $settings->getToggleHeader(),
            'toggleHero' => $settings->getToggleHero(),
            'toggleBreadcrumbs' => $settings->getToggleBreadcrumbs(),
            'pageGlossary' => $settings->getPageGlossary(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapDataToEntity(array $data, GlossaryEntrySettings $settings): void
    {
        $settings->setToggleHeader(isset($data['toggleHeader']) ? (bool) $data['toggleHeader'] : null);
        $settings->setToggleHero(isset($data['toggleHero']) ? (bool) $data['toggleHero'] : null);
        $settings->setToggleBreadcrumbs(isset($data['toggleBreadcrumbs']) ? (bool) $data['toggleBreadcrumbs'] : null);
        $settings->setPageGlossary(isset($data['pageGlossary']) ? (string) $data['pageGlossary'] : null);
    }
}
