<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Lists ACULTA answer attempts for an educator. */
final class AnswerReviewController extends ControllerBase {

  public function __construct(private readonly EntityTypeManagerInterface $reviewEntityTypeManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function list(GroupInterface $course, GroupInterface $group): array {
    $activityIds = [];
    foreach ($this->reviewEntityTypeManager->getStorage('aculta_lms_lesson')->loadByProperties(['course_id' => (int) $course->id()]) as $lesson) {
      foreach ($this->reviewEntityTypeManager->getStorage('aculta_lms_activity')->loadByProperties(['lesson_id' => (int) $lesson->id()]) as $activity) {
        $activityIds[] = (int) $activity->id();
      }
    }
    $rows = [];
    if ($activityIds !== []) {
      foreach ($this->reviewEntityTypeManager->getStorage('aculta_lms_answer')->loadByProperties(['activity_id' => $activityIds]) as $answer) {
        $rows[] = [
          ['data' => Link::fromTextAndUrl((string) $answer->id(), Url::fromRoute('aculta_lms.answer_review', ['course' => $course->id(), 'group' => $group->id(), 'answer' => $answer->id()]))->toRenderable()],
          ['data' => (string) $answer->get('uid')->value],
          ['data' => (string) $answer->get('state')->value],
          ['data' => $answer->get('score')->isEmpty() ? '' : (string) $answer->get('score')->value],
        ];
      }
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Resposta'), $this->t('Estudante'), $this->t('Estado'), $this->t('Nota')],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhuma resposta encontrada.'),
      '#cache' => ['max-age' => 0],
    ];
  }
}
