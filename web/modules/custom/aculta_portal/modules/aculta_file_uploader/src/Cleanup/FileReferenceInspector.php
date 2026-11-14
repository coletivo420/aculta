<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Cleanup;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityNullStorage;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Psr\Log\LoggerInterface;

/**
 * Checks structured and rich-text references to entities across revisions.
 */
final class FileReferenceInspector implements FileReferenceInspectorInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly Connection $database,
    private readonly ?LoggerInterface $logger = NULL,
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
      catch (\Throwable $exception) {
        return $this->unverifiable($entityTypeId, $exception);
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
        $fieldDefinitions = [];
        foreach ($definitions as $fieldName => $fieldDefinition) {
          $fieldDefinitions[] = [$fieldName, $fieldDefinition];
        }
        // Some entity types define reference settings per bundle instead of
        // on the shared field storage. Group's entity_id field is one such
        // case, so include bundle field definitions when available.
        $bundleEntityType = $definition->getBundleEntityType();
        if ($entityTypeId === 'group_relationship' && $bundleEntityType !== NULL) {
          $bundleStorage = $this->entityTypeManager->getStorage($bundleEntityType);
          foreach ($bundleStorage->loadMultiple() as $bundle) {
            foreach ($this->fieldManager->getFieldDefinitions($entityTypeId, $bundle->id()) as $fieldName => $fieldDefinition) {
              $fieldDefinitions[] = [$fieldName, $fieldDefinition];
            }
          }
        }
      }
      catch (\Throwable $exception) {
        return $this->unverifiable($entityTypeId, $exception);
      }

      foreach ($fieldDefinitions as [$fieldName, $definition]) {
        $type = $definition->getType();
        // Group stores the grouped entity in its own entity-reference field
        // type. It has the same target_type/target_id contract and must count
        // when deciding whether a Media or File is still in use.
        if (in_array($type, ['entity_reference', 'entity_reference_revisions', 'group_relationship_target'], TRUE)
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
          catch (\Throwable $exception) {
            return $this->unverifiable($entityTypeId, $exception);
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
          catch (\Throwable $exception) {
            return $this->unverifiable($entityTypeId, $exception);
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
          catch (\Throwable $exception) {
            return $this->unverifiable($entityTypeId, $exception);
          }
        }
      }
    }

    return $found ? FileReferenceStatus::Referenced : FileReferenceStatus::Clear;
  }

  /**
   * Logs an inspection failure and fails closed.
   */
  private function unverifiable(string $entityTypeId, \Throwable $exception): FileReferenceStatus {
    $this->logger?->warning('File reference inspection could not verify entity type @type: @exception.', [
      '@type' => $entityTypeId,
      '@exception' => get_class($exception),
    ]);
    return FileReferenceStatus::Unverifiable;
  }

}
