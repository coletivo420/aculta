<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Reads learner answers from ACULTA-owned entities. */
final class EntityCourseAnswerReader implements CourseAnswerReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc} */
  public function forStudent(int $activityId, int $uid): array {
    $entities = $this->entityTypeManager
      ->getStorage('aculta_lms_answer')
      ->loadByProperties([
        'activity_id' => $activityId,
        'uid' => $uid,
      ]);
    $answers = [];
    foreach ($entities as $entity) {
      if (!$entity instanceof ContentEntityInterface) {
        continue;
      }
      $score = self::fieldValue($entity, 'score');
      $answers[] = new CourseAnswer(
        id: (int) $entity->id(),
        activityId: (int) self::fieldValue($entity, 'activity_id'),
        uid: (int) self::fieldValue($entity, 'uid'),
        state: (string) self::fieldValue($entity, 'state'),
        score: is_numeric($score) ? (float) $score : NULL,
      );
    }
    usort($answers, static fn(CourseAnswer $a, CourseAnswer $b): int => $a->id <=> $b->id);

    return $answers;
  }

  /**
   * Lê o primeiro valor do campo informado da entidade.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entidade de onde o valor será lido.
   * @param string $field
   *   Nome do campo a consultar.
   *
   * @return mixed
   *   Valor do campo, ou NULL quando ausente.
   */
  private static function fieldValue(ContentEntityInterface $entity, string $field): mixed {
    $values = $entity->get($field)->getValue();
    return $values[0]['value'] ?? NULL;
  }

}
