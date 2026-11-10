<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Purpose;

/**
 * Lista canônica de purposes que o tema sabe apresentar.
 *
 * O tema não resolve domínio: recebe a chave neutra do Portal e valida contra
 * esta lista antes de aplicar classes ou variáveis de paleta.
 */
final class Purposes {

  /**
   * Purposes canônicos aprovados.
   *
   * @var list<string>
   */
  public const ALLOWED_PURPOSES = [
    'main',
    'account',
    'support',
    'magazine',
    'wiki',
    'shop',
    'courses',
    'links',
    'forum',
    'eventos',
  ];

}
