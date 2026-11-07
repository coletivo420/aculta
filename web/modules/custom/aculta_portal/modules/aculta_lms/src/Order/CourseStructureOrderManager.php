<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Order;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockAcquiringException;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Moves course lessons and lesson activities while preserving sibling order.
 */
final class CourseStructureOrderManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * Moves an item one position within its canonical parent collection.
   *
   * All siblings are normalized to contiguous zero-based weights in one
   * transaction. A collection lock serializes other move operations on the
   * same course or lesson.
   *
   * @return bool
   *   TRUE when the item moved, FALSE when it was already at that boundary.
   */
  public function move(string $entityType, int $parentId, int $itemId, string $direction): bool {
    $collections = [
      'aculta_lms_lesson' => 'course_id',
      'aculta_lms_activity' => 'lesson_id',
    ];
    if (!isset($collections[$entityType]) || $parentId < 1 || $itemId < 1
      || !in_array($direction, ['up', 'down'], TRUE)) {
      throw new \InvalidArgumentException('Invalid course structure move request.');
    }

    $parentField = $collections[$entityType];
    $lockName = 'aculta_lms.structure.' . $entityType . '.' . $parentId;
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new LockAcquiringException('The course structure is being reordered by another request.');
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($parentField, $parentId)
        ->sort('weight')
        ->sort('id')
        ->execute();
      $orderedIds = array_values(array_map('intval', $ids));
      $index = array_search($itemId, $orderedIds, TRUE);
      if ($index === FALSE) {
        throw new \InvalidArgumentException('The item does not belong to the requested parent.');
      }

      $destination = $index + ($direction === 'up' ? -1 : 1);
      if (!isset($orderedIds[$destination])) {
        return FALSE;
      }

      array_splice($orderedIds, $index, 1);
      array_splice($orderedIds, $destination, 0, [$itemId]);
      $entities = $storage->loadMultiple($orderedIds);
      $transaction = $this->database->startTransaction();
      foreach ($orderedIds as $weight => $id) {
        $entity = $entities[$id] ?? NULL;
        if ($entity === NULL || (int) $entity->get($parentField)->value !== $parentId) {
          throw new \InvalidArgumentException('The course structure changed while the move was being prepared.');
        }
        if ((int) $entity->get('weight')->value !== $weight) {
          $entity->set('weight', $weight);
          $entity->save();
        }
      }
      $transaction->commitOrRelease();
      return TRUE;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Persists an exact permutation of one course structure collection.
   *
   * @param string $entityType
   *   Tipo de entidade dos filhos reordenados.
   * @param int $parentId
   *   ID da entidade pai da coleção.
   * @param list<int> $orderedIds
   *   Complete ordered list of child entity IDs supplied by the editor.
   *
   * @return bool
   *   TRUE when the collection order changed.
   */
  public function reorder(string $entityType, int $parentId, array $orderedIds): bool {
    $parentFields = [
      'aculta_lms_lesson' => 'course_id',
      'aculta_lms_activity' => 'lesson_id',
    ];
    if (!isset($parentFields[$entityType]) || $parentId < 1 || $orderedIds === []) {
      throw new \InvalidArgumentException('Invalid course structure reorder request.');
    }
    $orderedIds = array_values($orderedIds);
    if (array_filter($orderedIds, static fn (mixed $id): bool => !is_int($id) || $id < 1) !== []
      || count(array_unique($orderedIds)) !== count($orderedIds)) {
      throw new \InvalidArgumentException('The course structure order contains invalid or duplicate IDs.');
    }

    $parentField = $parentFields[$entityType];
    $lockName = 'aculta_lms.structure.' . $entityType . '.' . $parentId;
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new LockAcquiringException('The course structure is being reordered by another request.');
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $currentIds = array_values(array_map('intval', $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($parentField, $parentId)
        ->sort('weight')
        ->sort('id')
        ->execute()));
      $expected = $currentIds;
      $submitted = $orderedIds;
      sort($expected, SORT_NUMERIC);
      sort($submitted, SORT_NUMERIC);
      if ($expected !== $submitted) {
        throw new \InvalidArgumentException('The course structure changed before the order was saved.');
      }
      if ($currentIds === $orderedIds) {
        return FALSE;
      }

      $entities = $storage->loadMultiple($orderedIds);
      $transaction = $this->database->startTransaction();
      foreach ($orderedIds as $weight => $id) {
        $entity = $entities[$id] ?? NULL;
        if ($entity === NULL || (int) $entity->get($parentField)->value !== $parentId) {
          throw new \InvalidArgumentException('The course structure changed while the new order was being prepared.');
        }
        if ((int) $entity->get('weight')->value !== $weight) {
          $entity->set('weight', $weight);
          $entity->save();
        }
      }
      $transaction->commitOrRelease();
      return TRUE;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

}
