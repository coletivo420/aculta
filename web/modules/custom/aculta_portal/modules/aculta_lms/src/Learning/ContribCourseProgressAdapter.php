<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\Bundle\Course;
use Drupal\lms\TrainingManager;

/**
 * Adaptador legado para progresso do LMS contrib, mantido até o fim da Fase A.
 */
final class ContribCourseProgressAdapter implements CourseProgressReaderInterface {

  public function __construct(
    private readonly TrainingManager $trainingManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function current(GroupInterface $course, AccountInterface $account): ?CourseProgress {
    // O contrib só carrega status para o bundle Course; qualquer outro grupo não tem progresso aqui.
    if (!$course instanceof Course) {
      return NULL;
    }
    $status = $this->trainingManager->loadCourseStatus($course, $account, ['current' => TRUE]);
    if ($status === NULL) {
      return NULL;
    }
    return new CourseProgress(
      status: (string) $status->getStatus(),
      finished: (bool) $status->isFinished(),
      score: is_numeric($status->getScore()) ? (float) $status->getScore() : NULL,
      cacheTags: $status->getCacheTags(),
    );
  }

}
