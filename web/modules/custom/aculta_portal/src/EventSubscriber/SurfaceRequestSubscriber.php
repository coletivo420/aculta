<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Responde 404 para superfície desativada na configuração (Fase 3.4).
 *
 * DESLIGADO por padrão: só age quando aculta_portal.superficies.leitura_ativa
 * for true. Ligar exige o teste de rota da 3.0 no Runtime (DEFERRED).
 * O MAIN nunca é afetado.
 */
final class SurfaceRequestSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly SurfaceConfigReader $surfaces,
    private readonly DomainPurposeManager $purposes,
  ) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => [['onRequest', 31]]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || !$this->surfaces->leituraAtiva()) {
      return;
    }
    $purpose = $this->purposes->getCurrentPurpose();
    if ($purpose === NULL || $purpose === DomainSurfaceMap::MAIN) {
      return;
    }
    $linha = $this->surfaces->superficies()[$purpose] ?? NULL;
    if ($linha !== NULL && (!$linha['ativa'] || $linha['modo'] === 'desativado')) {
      $event->setResponse(new Response('', Response::HTTP_NOT_FOUND, ['Cache-Control' => 'private, no-store']));
    }
  }

}
