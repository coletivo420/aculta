<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\PathProcessor;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Raiz (`/`) de cada purpose serve o conteúdo da página inicial daquele host, sem redirecionamento.
 *
 * Reescrita de caminho de ENTRADA (InboundPathProcessorInterface, tag path_processor_inbound), o mecanismo oficial
 * do Core para alterar o caminho antes do roteamento. A URL do navegador continua sendo `/`.
 * Decisão do responsável (2026-10-11): a raiz de cada host serve conteúdo; a exceção única (visitante da Conta vai
 * ao login) é tratada em DomainPurposeRequestSubscriber e não passa por aqui.
 *
 * Mapa único de páginas iniciais: acrescentar um purpose aqui é a única forma de definir a raiz dele.
 */
final class PurposeFrontPathProcessor implements InboundPathProcessorInterface {

  /** Purpose => caminho interno (nó ou rota) que serve a raiz do host. */
  public const FRONT_PATHS = [
    'shop' => '/loja-da-aculta',
  ];

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  public function processInbound($path, Request $request): string {
    if ($path !== '/') {
      return $path;
    }
    $purpose = $this->domainPurposeManager->getCurrentPurpose();
    if (!isset(self::FRONT_PATHS[$purpose])) {
      return $path;
    }
    // A raiz é a URL pública: o normalizador do módulo redirect (prioridade 30) não deve redirecionar o alias
    // interno para a URL canônica. Documentado pelo módulo redirect como _disable_route_normalizer.
    $request->attributes->set('_disable_route_normalizer', TRUE);
    return self::FRONT_PATHS[$purpose];
  }

}
