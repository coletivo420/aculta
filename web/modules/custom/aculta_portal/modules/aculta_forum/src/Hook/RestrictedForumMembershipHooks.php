<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\RestrictedForum\RestrictedForumMembershipSync;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Mantém a membership dos Groups restritos em sincronia com a associação.
 *
 * Qualquer gravação da marca de associado — ativação, suspensão,
 * desligamento, readmissão, convite aceito ou recusado, alteração por
 * administração — reconcilia a membership da conta. A decisão continua sendo
 * da situação `ativo`, então o hook é idempotente: repetir não duplica
 * membership.
 *
 * A sincronização é um efeito colateral: se o Group falhar, a gravação da
 * situação não é desfeita. O acesso já é revalidado em tempo real pela
 * política, então uma membership remanescente não concede leitura a conta não
 * ativa.
 */
final class RestrictedForumMembershipHooks {

  public function __construct(
    private readonly RestrictedForumMembershipSync $membershipSync,
  ) {}

  /**
   * Implements hook_aculta_associado_insert().
   */
  #[Hook('aculta_associado_insert')]
  public function associadoInsert(EntityInterface $marca): void {
    $this->sync($marca);
  }

  /**
   * Implements hook_aculta_associado_update().
   */
  #[Hook('aculta_associado_update')]
  public function associadoUpdate(EntityInterface $marca): void {
    $this->sync($marca);
  }

  /**
   * Implements hook_aculta_associado_delete().
   *
   * A marca já foi apagada, mas o campo `uid` ainda está no objeto carregado.
   */
  #[Hook('aculta_associado_delete')]
  public function associadoDelete(EntityInterface $marca): void {
    $this->sync($marca);
  }

  private function sync(EntityInterface $marca): void {
    if (!$marca instanceof FieldableEntityInterface || $marca->getEntityTypeId() !== 'aculta_associado' || !$marca->hasField('uid')) {
      return;
    }
    $values = $marca->get('uid')->getValue();
    $uid = isset($values[0]['target_id']) ? (int) $values[0]['target_id'] : 0;
    if ($uid > 0) {
      $this->membershipSync->syncAccount($uid);
    }
  }

}
