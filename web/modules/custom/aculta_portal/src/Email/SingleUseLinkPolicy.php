<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

/**
 * Validade dos links de acesso único do projeto: 24 horas, para todos os módulos (ver docs/portal/LINKS-DE-USO-UNICO.md).
 *
 * Fonte única da regra no Portal. O aculta_mailer tem a mesma constante (módulo não pode importar classe do Portal, que
 * depende dele); o gate scripts/validate-single-use-links.php confere que os dois valores são iguais.
 */
final class SingleUseLinkPolicy {

  /**
   * Validade, em segundos, de um link de acesso único.
   */
  public const TTL = 86400;

  /**
   * Indica se o link criado no instante informado já venceu no instante atual.
   */
  public static function isExpired(int $createdAt, int $now): bool {
    return $now - $createdAt >= self::TTL;
  }

}
