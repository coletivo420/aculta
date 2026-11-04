<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\aculta_lms\Entity\ActivityEntity;
use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\aculta_lms\Entity\LessonEntity;
use Drupal\aculta_lms\Group\GroupMembershipReaderInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists ACULTA answer attempts for an educator. */
final class AnswerReviewController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $reviewEntityTypeManager,
    private readonly GroupMembershipReaderInterface $memberships,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates the answer review controller with its required services. */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_lms.group_membership_reader'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the current class answer review listing. */
  public function list(GroupInterface $course, GroupInterface $group): array {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($course);
    $cacheability->addCacheableDependency($group);
    $cacheability->addCacheContexts(['user', 'user.group_permissions']);
    $cacheability->addCacheTags([
      'group_relationship_list:plugin:group_membership:group:' . $group->id(),
      'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
    ]);

    $studentIds = [];
    $students = [];
    foreach ($this->memberships->loadByGroup($group) as $membership) {
      if (!$membership instanceof GroupRelationshipInterface || $membership->getPluginId() !== 'group_membership') {
        continue;
      }
      $student = $membership->getEntity();
      if (!$student instanceof UserInterface || !$student->isActive()) {
        continue;
      }
      $studentIds[] = (int) $student->id();
      $students[(int) $student->id()] = $student;
      $cacheability->addCacheableDependency($membership);
      $cacheability->addCacheableDependency($student);
    }

    $activities = [];
    $activityStorage = $this->reviewEntityTypeManager->getStorage('aculta_lms_activity');
    $lessonStorage = $this->reviewEntityTypeManager->getStorage('aculta_lms_lesson');
    foreach ($lessonStorage->loadByProperties(['course_id' => (int) $course->id()]) as $lesson) {
      if (!$lesson instanceof LessonEntity) {
        continue;
      }
      $cacheability->addCacheableDependency($lesson);
      foreach ($activityStorage->loadByProperties(['lesson_id' => (int) $lesson->id()]) as $activity) {
        if (!$activity instanceof ActivityEntity) {
          continue;
        }
        $activities[(int) $activity->id()] = [
          'entity' => $activity,
          'lesson' => $lesson,
        ];
        $cacheability->addCacheableDependency($activity);
      }
    }

    $rows = [];
    if ($activities !== [] && $studentIds !== []) {
      $answerStorage = $this->reviewEntityTypeManager->getStorage('aculta_lms_answer');
      $answerIds = $answerStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('activity_id', array_keys($activities), 'IN')
        ->condition('uid', $studentIds, 'IN')
        ->sort('created', 'DESC')
        ->sort('id', 'DESC')
        ->pager(50)
        ->execute();
      $answers = array_values($answerStorage->loadMultiple($answerIds));
      foreach ($answers as $answer) {
        if (!$answer instanceof AnswerEntity) {
          continue;
        }
        $studentId = (int) $answer->get('uid')->value;
        $activityId = (int) $answer->get('activity_id')->value;
        if (!isset($students[$studentId], $activities[$activityId])) {
          continue;
        }
        $activity = $activities[$activityId]['entity'];
        $lesson = $activities[$activityId]['lesson'];
        $cacheability->addCacheableDependency($answer);
        $status = match ((string) $answer->get('state')->value) {
          'graded' => $this->t('Avaliada'),
          default => $this->t('Aguardando avaliação'),
        };
        $rows[] = [
          ['data' => ['#plain_text' => $students[$studentId]->getDisplayName()]],
          ['data' => ['#plain_text' => $lesson->get('title')->value]],
          ['data' => ['#plain_text' => $this->t('Atividade @type', ['@type' => str_replace('_', ' ', (string) $activity->get('type')->value)])]],
          ['data' => ['#plain_text' => $this->dateFormatter->format((int) $answer->get('created')->value, 'short')]],
          ['data' => ['#plain_text' => $status]],
          ['data' => ['#plain_text' => $answer->get('score')->isEmpty() ? '' : (string) $answer->get('score')->value]],
          ['data' => Link::fromTextAndUrl($this->t('Abrir resposta'), Url::fromRoute('aculta_lms.answer_review', [
            'course' => $course->id(),
            'group' => $group->id(),
            'answer' => $answer->id(),
          ]))->toRenderable()],
        ];
      }
    }

    $build = [
      '#type' => 'container',
      'answers' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Estudante'),
          $this->t('Aula'),
          $this->t('Atividade'),
          $this->t('Enviada'),
          $this->t('Estado'),
          $this->t('Nota'),
          $this->t('Ação'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma resposta encontrada.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
    $cacheability->setCacheMaxAge(0)->applyTo($build);
    return $build;
  }

}
