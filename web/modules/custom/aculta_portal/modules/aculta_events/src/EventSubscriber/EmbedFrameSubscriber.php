<?php

declare(strict_types=1);

namespace Drupal\aculta_events\EventSubscriber;

use Drupal\aculta_events\Embed\EmbedHostAllowlist;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Política de incorporação só para a agenda embutível (aculta_events.embed).
 *
 * Só sites da própria ACULTA podem incorporar: os hosts de teste e de produção do mapeamento
 * do aculta_deployer (EmbedHostAllowlist). O restante do site mantém a política padrão. Roda depois do
 * core e do módulo CSP, que removem ou reescrevem esses cabeçalhos.
 */
final class EmbedFrameSubscriber implements EventSubscriberInterface {

  /**
   * Rota da agenda incorporável.
   */
  public const ROTA = 'aculta_events.embed';

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EmbedHostAllowlist $hostsAutorizados,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -100]];
  }

  /**
   * Ajusta os cabeçalhos da resposta da agenda incorporável.
   */
  public function onResponse(ResponseEvent $evento): void {
    if (!$evento->isMainRequest() || $evento->getRequest()->attributes->get('_route') !== self::ROTA) {
      return;
    }
    $resposta = $evento->getResponse();
    $resposta->headers->remove('X-Frame-Options');
    $resposta->headers->set('Content-Security-Policy', self::politica($this->hostsAutorizados->hosts()));
  }

  /**
   * Política de frame: 'self' e os domínios da própria ACULTA.
   *
   * @param string[] $hosts
   */
  public static function politica(array $hosts): string {
    $origens = array_map(static fn (string $host): string => 'https://' . $host, array_values(array_unique($hosts)));
    return 'frame-ancestors ' . implode(' ', array_merge(["'self'"], $origens));
  }


}
