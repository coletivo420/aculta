<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\user\UserInterface;

/**
 * Concede o papel estudante a quem se matricula em qualquer curso.
 *
 * Decisão do responsável (2026-10-17): estudante é garantido ao se inscrever em qualquer curso. A matrícula é a relação
 * de grupo do plugin group_membership no tipo lms_course. O papel de aluno do curso (lms_course-member, escopo insider) é
 * implícito e não fica gravado em group_roles, por isso o critério é a própria matrícula. O papel não é removido quando a
 * pessoa sai do curso, porque a concessão é garantida. O associado não é concedido aqui: é atribuído manualmente.
 *
 * A gravação da conta ocorre na relação de grupo, e não dentro de um hook de usuário, para não interferir no original
 * usado pelo módulo group (ver RoleSyncHooks).
 */
final class EnrollmentRoleHooks {

  /** Papel concedido ao se matricular. */
  private const ROLE = 'estudante';

  /** Tipo de grupo dos cursos. */
  private const GROUP_TYPE_CURSO = 'lms_course';

  /**
   * Implements hook_group_relationship_insert().
   */
  #[Hook('group_relationship_insert')]
  public function groupRelationshipInsert(GroupRelationshipInterface $relationship): void {
    if ($relationship->getPluginId() !== 'group_membership' || $relationship->getGroupTypeId() !== self::GROUP_TYPE_CURSO) {
      return;
    }
    $conta = $relationship->getEntity();
    if (!$conta instanceof UserInterface || $conta->isAnonymous() || $conta->hasRole(self::ROLE)) {
      return;
    }
    $conta->addRole(self::ROLE);
    $conta->save();
  }

}
