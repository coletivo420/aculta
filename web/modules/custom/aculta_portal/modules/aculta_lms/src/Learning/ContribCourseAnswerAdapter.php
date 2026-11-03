<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\TrainingManager;

/** Legacy adapter retained until the end of ADR-042 Phase A. */
final class ContribCourseAnswerAdapter implements CourseAnswerReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TrainingManager $trainingManager,
  ) {}

  /** {@inheritdoc} */
  public function forStudent(int $activityId, int $uid): array {
    $storage = $this->entityTypeManager->getStorage('lms_answer');
    $answers = $storage->loadByProperties([
      AnswerInterface::ACTIVITY_FIELD => $activityId,
      'user_id' => $uid,
    ]);

    $result = [];
    foreach ($answers as $answer) {
      if ($answer instanceof AnswerInterface) {
        $result[] = self::fromContrib($answer);
      }
    }
    return $result;
  }

  /** Converts one contrib answer. */
  public function fromContrib(AnswerInterface $answer): CourseAnswer {
    $evaluated = $answer->isEvaluated();
    $state = self::stateFromEvaluated($evaluated);
    $activity = $answer->getActivity();
    $scoreField = $answer->get('score');
    $score = NULL;
    if ($evaluated) {
      if ($scoreField->isEmpty()) {
        throw new \UnexpectedValueException('Resposta avaliada do LMS contrib não possui nota.');
      }

      $maxScore = $this->trainingManager->getActivityMaxScore(
        $answer->getLessonStatus()->getLesson(),
        $activity,
      );
      if ($maxScore <= 0) {
        throw new \UnexpectedValueException('A atividade do LMS contrib não possui pontuação máxima para conversão.');
      }
      $score = (float) $answer->getScore() / $maxScore * 100;
    }

    return new CourseAnswer(
      id: (int) $answer->id(),
      activityId: (int) $activity->id(),
      uid: (int) $answer->getUserId(),
      state: $state,
      score: $score,
    );
  }

  /** Maps the contrib evaluation flag to the two ACULTA answer states. */
  public static function stateFromEvaluated(mixed $evaluated): string {
    if ($evaluated === TRUE) {
      return CourseAnswer::STATE_GRADED;
    }
    if ($evaluated === FALSE) {
      return CourseAnswer::STATE_SUBMITTED;
    }
    throw new \UnexpectedValueException('Estado de avaliação do LMS contrib desconhecido.');
  }

}
