<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Lms;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Lê as turmas do curso e as matrículas da conta, sem gravar nada.
 *
 * Relacionamento `lms_classes`: entidade = turma, grupo = curso. Matrícula em turma:
 * relacionamento `group_membership`, entidade = conta, grupo = turma. A conta só é lida
 * para a consulta; o resultado é o identificador da turma.
 */
final class LmsClassResolver implements LmsClassResolverInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function classIdFor(int $courseId, int $accountId): ?int {
    $storage = $this->entityTypeManager->getStorage('group_relationship');

    $classIds = [];
    foreach ($storage->loadByProperties(['plugin_id' => 'lms_classes', 'gid' => $courseId]) as $relationship) {
      $classIds[(int) $relationship->get('entity_id')->target_id] = TRUE;
    }
    if ($classIds === []) {
      return NULL;
    }

    $memberOf = [];
    foreach ($storage->loadByProperties(['plugin_id' => 'group_membership', 'entity_id' => $accountId]) as $membership) {
      $groupId = (int) $membership->get('gid')->target_id;
      if (isset($classIds[$groupId])) {
        $memberOf[$groupId] = TRUE;
      }
    }

    return count($memberOf) === 1 ? (int) array_key_first($memberOf) : NULL;
  }

}
