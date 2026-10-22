<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Registra a categoria estudante para quem se matricula em qualquer curso ou turma.
 *
 * Decisão do responsável (2026-10-17): estudante é garantido ao se inscrever em qualquer curso. A matrícula é a relação
 * de grupo do plugin group_membership no tipo lms_course ou lms_class. Só o aluno recebe a categoria: a matrícula com papel de professor,
 * criador ou administração do LMS (group_roles) não concede estudante, porque a matrícula de aluno já o distingue. O papel de aluno (lms_course-member, escopo insider) é implícito e não fica gravado em group_roles.
 * A categoria não é removida quando a pessoa sai do curso, porque a concessão é garantida. Associado e estudante podem coexistir.
 *
 * A gravação da conta ocorre na relação de grupo, e não dentro de um hook de usuário, para não interferir no original
 * usado pelo módulo group.
 */
final class EnrollmentRoleHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Tipo de grupo dos cursos. */
  private const GROUP_TYPE_CURSO = 'lms_course';

  /** Tipo de grupo de turmas. */
  private const GROUP_TYPE_TURMA = 'lms_class';

  /** Papéis de grupo que não são de aluno: a matrícula com algum deles não concede estudante. */
  private const PAPEIS_NAO_ALUNO = [
    'lms_course-teacher',
    'lms_course-course_creator',
    'lms_course-lms_admin',
    'lms_course-lms_admin_member',
    'lms_class-teacher',
    'lms_class-lms_admin',
    'lms_class-lms_admin_member',
  ];

  /**
   * Implements hook_group_relationship_insert().
   */
  #[Hook('group_relationship_insert')]
  public function groupRelationshipInsert(GroupRelationshipInterface $relationship): void {
    if ($relationship->getPluginId() !== 'group_membership' || !\in_array($relationship->getGroupTypeId(), [self::GROUP_TYPE_CURSO, self::GROUP_TYPE_TURMA], TRUE)) {
      return;
    }
    $papeisDeGrupo = array_column($relationship->get('group_roles')->getValue(), 'target_id');
    if (array_intersect($papeisDeGrupo, self::PAPEIS_NAO_ALUNO) !== []) {
      return;
    }
    $conta = $relationship->getEntity();
    if (!$conta instanceof UserInterface || $conta->isAnonymous()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('profile');
    $existing = $storage->getQuery()->accessCheck(FALSE)
      ->condition('uid', $conta->id())->condition('type', 'estudante')->range(0, 1)->execute();
    if ($existing === []) {
      $storage->create(['type' => 'estudante', 'uid' => $conta->id()])->save();
    }
  }

}
