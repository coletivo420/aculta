<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher;

/**
 * Monta a linha de auditoria de uma alteração de patch.
 *
 * Registra quem, o quê e em qual estado, sem o conteúdo do diff: o diff pode ser grande e não é
 * necessário para rastrear a decisão.
 */
final class PatchAuditMessage {

  /**
   * Ações aceitas na trilha de auditoria.
   */
  public const ACTIONS = ['criado', 'alterado', 'excluído'];

  /**
   * Monta a linha de auditoria.
   *
   * @throws \InvalidArgumentException
   *   Quando a ação não pertence a ACTIONS.
   */
  public static function format(string $action, string $id, string $package, string $version, bool $active, int $uid): string {
    if (!in_array($action, self::ACTIONS, TRUE)) {
      throw new \InvalidArgumentException("Ação de auditoria desconhecida: $action");
    }
    return sprintf(
      'Patch %s %s: pacote %s %s, %s, por usuário %d.',
      $id,
      $action,
      $package,
      $version,
      $active ? 'ativo' : 'inativo',
      $uid,
    );
  }

}
