<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_lms\Event\CourseCompletionPublisherInterface;
use Drupal\aculta_lms\Entity\ProgressEntity;
use Drupal\aculta_lms\Learning\CourseProgress;

/**
 * Publishes only a transition into the persisted ACULTA passed state.
 */
final class CourseStatusEventHooks {

  public function __construct(
    private readonly CourseCompletionPublisherInterface $publisher,
  ) {}

  /**
 * Publishes a newly created status when it is already passed and finished. */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof ProgressEntity) {
      $this->publisher->publish($entity);
    }
  }

  /**
 * Publishes only when an existing status changes into passed and finished. */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof ProgressEntity) {
      return;
    }
    $original = $entity->getOriginal();
    if (!$original instanceof ProgressEntity
      || ((bool) self::fieldValue($original, 'finished') && (string) self::fieldValue($original, 'status') === CourseProgress::STATUS_PASSED)
      || !(bool) self::fieldValue($entity, 'finished')
      || (string) self::fieldValue($entity, 'status') !== CourseProgress::STATUS_PASSED) {
      return;
    }
    $this->publisher->publish($entity);
  }

  /**
   * Retorna o valor de um campo da entidade de progresso.
   *
   * @param \Drupal\aculta_lms\Entity\ProgressEntity $progress
   *   Entidade de progresso.
   * @param string $field
   *   Nome do campo.
   *
   * @return mixed
   *   Valor do campo, ou NULL quando ausente.
   */
  private static function fieldValue(ProgressEntity $progress, string $field): mixed {
    $values = $progress->get($field)->getValue();
    return $values[0]['value'] ?? NULL;
  }

}
