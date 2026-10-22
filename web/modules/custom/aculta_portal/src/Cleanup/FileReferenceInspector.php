<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cleanup;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

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
   * Reports whether an active field or formatted-text field references a File.
   */
  public function inspect(EntityInterface $target): FileReferenceStatus {
    $targetType = $target->getEntityTypeId();
    $found = FALSE;
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if (!$entityType->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }
      try {
        $definitions = $this->fieldManager->getFieldStorageDefinitions($entityTypeId);
        $storage = $this->entityTypeManager->getStorage($entityTypeId);
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
          $status = $this->inspectTextField($entityType->isRevisionable(), $fieldName, $definition, $storage, $target);
          if ($status === FileReferenceStatus::Unverifiable) {
            return $status;
          }
          $found = $found || $status === FileReferenceStatus::Referenced;
        }
      }
    }

    return $found ? FileReferenceStatus::Referenced : FileReferenceStatus::Clear;
  }

  /**
   * Checks a formatted-text field in its current and revision storage tables.
   */
  private function inspectTextField(bool $revisionable, string $fieldName, FieldStorageDefinitionInterface $definition, EntityStorageInterface $storage, EntityInterface $target): FileReferenceStatus {
    if (!method_exists($storage, 'getTableMapping')) {
      return FileReferenceStatus::Unverifiable;
    }
    try {
      $mapping = $storage->getTableMapping();
      $column = $mapping->getFieldColumnName($definition, 'value');
      $tables = [$mapping->getFieldTableName($fieldName)];
      if ($revisionable && method_exists($mapping, 'getDedicatedRevisionTableName')) {
        $tables[] = $mapping->getDedicatedRevisionTableName($definition);
      }
    }
    catch (\Throwable) {
      return FileReferenceStatus::Unverifiable;
    }

    foreach (array_unique($tables) as $table) {
      if (!$this->database->schema()->tableExists($table)
        || !$this->database->schema()->fieldExists($table, $column)) {
        return FileReferenceStatus::Unverifiable;
      }
      try {
        $query = $this->database->select($table, 'f')
          ->fields('f', [$column])
          ->condition('f.' . $column, '%' . $this->database->escapeLike($target->uuid()) . '%', 'LIKE')
          ->range(0, 1);
        if ($query->execute()->fetchField() !== FALSE) {
          return FileReferenceStatus::Referenced;
        }
      }
      catch (\Throwable) {
        return FileReferenceStatus::Unverifiable;
      }
    }

    return FileReferenceStatus::Clear;
  }

}
