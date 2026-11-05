<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

/**
 * Mapa único de superfícies do aculta_portal (Fase 3.1, sem mudança de valor).
 *
 * Antes desta classe, os mapas viviam em DomainPurposeManager, em
 * PurposeFrontPathProcessor e em FrontPathPolicy. Os valores são os mesmos.
 * Quem consome deve ler daqui. A configuração por ambiente virá nas fases
 * seguintes (docs do submódulo aculta_paths).
 */
final class DomainSurfaceMap {

  /**
   * Superfície protegida: domínio principal (painel, carrinho, pagamento).
   */
  public const MAIN = 'main';

  /**
   * Superfície de Conta.
   */
  public const ACCOUNT = 'account';

  /**
   * Purpose => ID do Domain que o representa.
   */
  public const DOMAIN_IDS = [
    'main' => 'aculta_org',
    'account' => 'conta_aculta_org',
    'support' => 'apoio_aculta_org',
    'magazine' => 'revista420_aculta_org',
    'wiki' => 'wiki420_aculta_org',
    'shop' => 'loja_aculta_org',
    'courses' => 'cursos_aculta_org',
    'forum' => 'forum_aculta_org',
    'links' => 'links_aculta_org',
    'eventos' => 'eventos_aculta_org',
  ];

  /**
   * Purpose => caminho interno que serve a raiz do host.
   */
  public const FRONT_PATHS = [
    'support' => '/apoio',
    'wiki' => '/wiki',
    'courses' => '/cursos',
    'shop' => '/loja',
    'forum' => '/discussoes',
    'links' => '/redes-sociais',
    // Internal route used to serve the Studio dashboard at the account root.
    'account' => '/aculta-studio-dashboard',
    'eventos' => '/eventos',
  ];

  /**
   * Purpose => caminho do nó que é a página inicial do host (não público).
   */
  public const NO_DA_RAIZ = [
    'shop' => '/node/372',
  ];

  /**
   * Purpose => caminhos internos legados que permanecem fechados.
   */
  public const CAMINHOS_LEGADOS = [
    'forum' => ['/forum'],
  ];

}
