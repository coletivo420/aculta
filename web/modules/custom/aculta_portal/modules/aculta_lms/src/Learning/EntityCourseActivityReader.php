<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Reads lesson activities from ACULTA-owned entities. */
final class EntityCourseActivityReader implements CourseActivityReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc} */
  public function forLesson(int $lessonId): array {
    $entities = $this->entityTypeManager
      ->getStorage('aculta_lms_activity')
      ->loadByProperties(['lesson_id' => $lessonId]);
    $activities = [];
    foreach ($entities as $entity) {
      $activities[] = [
        'activity' => new CourseActivity(
          id: (int) $entity->id(),
          lessonId: (int) self::fieldValue($entity, 'lesson_id'),
          type: (string) self::fieldValue($entity, 'type'),
          graded: (bool) self::fieldValue($entity, 'graded'),
        ),
        'weight' => (int) self::fieldValue($entity, 'weight'),
      ];
    }
    usort($activities, static fn(array $a, array $b): int => [
      $a['weight'],
      $a['activity']->id,
    ] <=> [
      $b['weight'],
      $b['activity']->id,
    ]);

    return array_column($activities, 'activity');
  }

  /**
   * Lê o primeiro valor de um campo da entidade.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entidade de onde o valor será lido.
   * @param string $field
   *   Nome do campo consultado.
   *
   * @return mixed
   *   Primeiro valor do campo ou nulo quando vazio.
   */
  private static function fieldValue(ContentEntityInterface $entity, string $field): mixed {
    $values = $entity->get($field)->getValue();
    return $values[0]['value'] ?? NULL;
  }

}
