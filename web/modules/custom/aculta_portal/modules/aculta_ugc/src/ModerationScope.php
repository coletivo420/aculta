<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc;

use Drupal\Core\Session\AccountInterface;

/**
 * Escopo de moderação por purpose (ARQ-06A).
 *
 * A permissão geral `moderar denuncias ugc` cobre todos os purposes (papel
 * transversal). Cada purpose tem também uma permissão própria, para que a
 * equipe de uma área não decida denúncias de outra (D-03, D-24).
 */
final class ModerationScope {

  /**
   * Permissão transversal: modera todos os purposes.
   */
  public const GLOBAL_PERMISSION = 'moderar denuncias ugc';

  /**
   * Purposes que aceitam denúncia.
   */
  public const PURPOSES = ['portal', 'eventos', 'forum', 'revista', 'wiki'];

  /**
   * Nome da permissão que modera um purpose.
   */
  public static function permissionFor(string $purpose): string {
    return self::GLOBAL_PERMISSION . ': ' . $purpose;
  }

  /**
   * Indica se a conta modera todos os purposes.
   */
  public static function isGlobal(AccountInterface $account): bool {
    return $account->hasPermission(self::GLOBAL_PERMISSION);
  }

  /**
   * Indica se a conta pode moderar denúncias do purpose.
   */
  public static function allows(AccountInterface $account, string $purpose): bool {
    if (!in_array($purpose, self::PURPOSES, TRUE)) {
      // Purpose desconhecido só é moderado pelo escopo transversal.
      return self::isGlobal($account);
    }
    return self::isGlobal($account) || $account->hasPermission(self::permissionFor($purpose));
  }

  /**
   * Purposes que a conta pode moderar.
   *
   * @return string[]
   *   Purposes em ordem estável.
   */
  public static function purposesFor(AccountInterface $account): array {
    return array_values(array_filter(
      self::PURPOSES,
      static fn (string $purpose): bool => self::allows($account, $purpose),
    ));
  }

}
