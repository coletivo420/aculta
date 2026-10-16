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
 * de grupo do plugin group_membership no tipo lms_course. Só o aluno recebe o papel: a matrícula com papel de professor,
 * criador ou administração do LMS (group_roles) não concede estudante, porque o professor já tem acesso pela hierarquia.
 * O papel de aluno (lms_course-member, escopo insider) é implícito e não fica gravado em group_roles. O papel não é
 * removido quando a pessoa sai do curso, porque a concessão é garantida. O associado não é concedido aqui.
 *
 * A gravação da conta ocorre na relação de grupo, e não dentro de um hook de usuário, para não interferir no original
 * usado pelo módulo group (ver RoleSyncHooks).
 */
final class EnrollmentRoleHooks {

  /** Papel concedido ao se matricular. */
  private const ROLE = 'estudante';

  /** Tipo de grupo dos cursos. */
  private const GROUP_TYPE_CURSO = 'lms_course';

  /** Papéis de grupo que não são de aluno: a matrícula com algum deles não concede estudante. */
  private const PAPEIS_NAO_ALUNO = [
    'lms_course-teacher',
    'lms_course-course_creator',
    'lms_course-lms_admin',
    'lms_course-lms_admin_member',
  ];

  /**
   * Implements hook_group_relationship_insert().
   */
  #[Hook('group_relationship_insert')]
  public function groupRelationshipInsert(GroupRelationshipInterface $relationship): void {
    if ($relationship->getPluginId() !== 'group_membership' || $relationship->getGroupTypeId() !== self::GROUP_TYPE_CURSO) {
      return;
    }
    $papeisDeGrupo = array_column($relationship->get('group_roles')->getValue(), 'target_id');
    if (array_intersect($papeisDeGrupo, self::PAPEIS_NAO_ALUNO) !== []) {
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
