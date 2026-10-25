<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cleanup;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityNullStorage;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Checks structured and rich-text references to entities across revisions.
 */
final class FileReferenceInspector implements FileReferenceInspectorInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly Connection $database,
  ) {}

  /**
   * Reports whether an active field or formatted-text field references target.
   */
  public function inspect(EntityInterface $target): FileReferenceStatus {
    $targetType = $target->getEntityTypeId();
    $found = FALSE;
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if (!$entityType->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }
      try {
        $definition = $this->entityTypeManager->getDefinition($entityTypeId);
        $storage = $this->entityTypeManager->getStorage($entityTypeId);
      }
      catch (\Throwable) {
        return FileReferenceStatus::Unverifiable;
      }

      // A registered entity type whose storage was never installed cannot
      // hold references in this installation. Skipping it is not an
      // authorization to delete: there is no base table to contain rows.
      $baseTable = $definition->getBaseTable();
      if ($baseTable === NULL) {
        // A null-storage entity type (for example a preview stub) never
        // persists rows, so it cannot reference anything. Any other storage
        // without a base table cannot be verified and must fail closed.
        if ($storage instanceof ContentEntityNullStorage) {
          continue;
        }
        return FileReferenceStatus::Unverifiable;
      }
      try {
        if (!$this->database->schema()->tableExists($baseTable)) {
          continue;
        }
        $definitions = $this->fieldManager->getFieldStorageDefinitions($entityTypeId);
      }
      catch (\Throwable) {
        return FileReferenceStatus::Unverifiable;
      }

      foreach ($definitions as $fieldName => $definition) {
        $type = $definition->getType();
        if (in_array($type, ['entity_reference', 'entity_reference_revisions'], TRUE)
          && $definition->getSetting('target_type') === $targetType) {
          try {
            $query = $storage->getQuery()->accessCheck(FALSE);
            if ($entityType->isRevisionable()) {
              $query->allRevisions();
            }
            if ($query->condition($fieldName . '.target_id', (string) $target->id())->range(0, 1)->execute() !== []) {
              $found = TRUE;
            }
          }
          catch (\Throwable) {
            return FileReferenceStatus::Unverifiable;
          }
        }

        if ($targetType === 'file' && in_array($type, ['file', 'image'], TRUE)) {
          try {
            $query = $storage->getQuery()->accessCheck(FALSE);
            if ($entityType->isRevisionable()) {
              $query->allRevisions();
            }
            if ($query->condition($fieldName . '.target_id', (string) $target->id())->range(0, 1)->execute() !== []) {
              $found = TRUE;
            }
          }
          catch (\Throwable) {
            return FileReferenceStatus::Unverifiable;
          }
        }

        if (in_array($type, ['text', 'text_long', 'text_with_summary'], TRUE)) {
          try {
            $query = $storage->getQuery()->accessCheck(FALSE);
            if ($entityType->isRevisionable()) {
              $query->allRevisions();
            }
            $matches = $query->condition(
              $fieldName . '.value',
              '%' . $this->database->escapeLike($target->uuid()) . '%',
              'LIKE',
            )->range(0, 1)->execute();
            if ($matches !== []) {
              $found = TRUE;
            }
          }
          catch (\Throwable) {
            return FileReferenceStatus::Unverifiable;
          }
        }
      }
    }

    return $found ? FileReferenceStatus::Referenced : FileReferenceStatus::Clear;
  }

}
