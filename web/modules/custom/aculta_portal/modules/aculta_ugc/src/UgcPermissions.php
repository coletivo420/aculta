<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Permissões de moderação por purpose (ARQ-06A).
 */
final class UgcPermissions {

  use StringTranslationTrait;

  /**
   * Gera uma permissão de moderação para cada purpose.
   *
   * @return array<string, array<string, mixed>>
   *   Definições de permissão.
   */
  public function permissions(): array {
    $permissions = [];
    foreach (ModerationScope::PURPOSES as $purpose) {
      $permissions[ModerationScope::permissionFor($purpose)] = [
        'title' => $this->t('Moderar denúncias de UGC do purpose @purpose', ['@purpose' => $purpose]),
        'description' => $this->t('Permite ver a fila e decidir denúncias somente deste purpose.'),
        'restrict access' => TRUE,
      ];
    }
    return $permissions;
  }

}
