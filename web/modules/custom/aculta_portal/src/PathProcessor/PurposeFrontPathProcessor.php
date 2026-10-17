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

  /** Atributo da requisição que marca a raiz reescrita (permite rotas públicas que só respondem em `/`). */
  public const FRONT_ATTRIBUTE = '_aculta_purpose_front';

  /**
   * Purpose => caminho interno que serve a raiz do host (mapa único).
   *
   * - support: a raiz serve o formulário de apoio (rota `aculta_portal.support_form`, `/apoio`).
   * - wiki e courses: a raiz serve a página inicial da wiki (`/wiki`) e dos cursos (`/cursos`). Verificado: texto igual.
   * - shop: a raiz serve a página inicial da loja (rota `aculta_portal.shop_home`, `/loja`, que mostra o nó 372).
   * - forum: a raiz serve o índice do fórum (rota `forum.index`, `/forum`).
   * - magazine e main: sem reescrita. A raiz é o `<front>` (página inicial do site, com a marca do host).
   */
  public const FRONT_PATHS = [
    'support' => '/apoio',
    'wiki' => '/wiki',
    'courses' => '/cursos',
    'shop' => '/loja',
    'forum' => '/forum',
    'links' => '/redes-sociais',
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
    $request->attributes->set(self::FRONT_ATTRIBUTE, TRUE);
    return self::FRONT_PATHS[$purpose];
  }

}
