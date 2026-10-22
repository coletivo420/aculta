<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\aculta_portal\Cleanup\FileCleanupStatus;
use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\file\FileInterface;

/**
 * Limpeza segura de imagens substituídas e uploads temporários vencidos. */
final class ImageCleanup {

  private const QUEUE = 'aculta_image_replaced_files';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
    private readonly FileRemover $fileRemover,
    private readonly ?UnusedFileCleaner $unusedFileCleaner = NULL,
  ) {}

  /**
   * Registra arquivos que deixaram de estar em campos de arquivo/imagem de uma entidade. */
  public function enqueueReplaced(EntityInterface $entity): void {
    $original = $entity->getOriginal();
    if ($original === NULL || !$entity->getEntityType()->entityClassImplements('Drupal\\Core\\Entity\\FieldableEntityInterface')) {
      return;
    }
    $old = $this->fieldFileIds($original);
    $new = $this->fieldFileIds($entity);
    foreach (array_diff($old, $new) as $fid) {
      $this->enqueue((int) $fid);
    }
  }

  /**
   * Registra arquivos de campos quando a entidade proprietária é excluída. */
  public function enqueueDeleted(EntityInterface $entity): void {
    if (!$entity->getEntityType()->entityClassImplements('Drupal\\Core\\Entity\\FieldableEntityInterface')) {
      return;
    }
    foreach ($this->fieldFileIds($entity) as $fid) {
      $this->enqueue($fid);
    }
    $this->enqueueEntityUsage($entity);
  }

  /**
   * Enfileira referências de texto rico antes que a atualização do editor as remova. */
  public function enqueueChangedText(EntityInterface $entity): void {
    $original = $entity->getOriginal();
    if ($original === NULL || !$entity->getEntityType()->entityClassImplements('Drupal\\Core\\Entity\\FieldableEntityInterface') || !$this->formattedTextChanged($original, $entity)) {
      return;
    }
    $this->enqueueEntityUsage($entity);
  }

  /**
   * Enfileira todas as imagens associadas ao registro no momento de remover a entidade. */
  public function enqueueBeforeDelete(EntityInterface $entity): void {
    if ($entity->id() !== NULL) {
      $this->enqueueEntityUsage($entity);
    }
  }

  /**
   * Processa a fila de substituições e temporários de imagem vencidos. */
  public function run(int $limit = 100): array {
    $queue = $this->queueFactory->get(self::QUEUE, TRUE);
    $deleted = 0;
    $processed = 0;
    while ($processed < $limit && ($item = $queue->claimItem(60)) !== FALSE) {
      $processed++;
      $file = $this->loadImage((int) ($item->data['fid'] ?? 0));
      if ($file !== NULL && $this->removeFile($file)) {
        $deleted++;
      }
      $queue->deleteItem($item);
    }

    $remaining = max(0, $limit - $processed);
    if ($remaining > 0) {
      $deleted += $this->deleteExpiredTemporaryImages($remaining);
    }
    return ['processed' => $processed, 'deleted' => $deleted];
  }

  /**
   * Gets the minimum age inherited from File API (six hours by default).
   */
  public function temporaryAge(): int {
    $age = (int) $this->configFactory->get('system.file')->get('temporary_maximum_age');
    return $age > 0 ? $age : 21600;
  }

  /**
   * Adds a File ID to the cleanup queue.
   */
  private function enqueue(int $fid): void {
    if ($fid > 0) {
      $this->queueFactory->get(self::QUEUE, TRUE)->createItem(['fid' => $fid]);
    }
  }

  /**
   * Adds File Usage targets for an entity to the cleanup queue.
   */
  private function enqueueEntityUsage(EntityInterface $entity): void {
    if ($entity->id() === NULL) {
      return;
    }
    $entityType = $entity->getEntityTypeId();
    $entityId = (string) $entity->id();
    $fids = $this->database->select('file_usage', 'fu')
      ->fields('fu', ['fid'])
      ->condition('fu.type', $entityType)
      ->condition('fu.id', $entityId)
      ->execute()
      ->fetchCol();
    foreach ($fids as $fid) {
      $this->enqueue((int) $fid);
    }
    $targets = $this->database->select('entity_usage', 'eu')
      ->fields('eu', ['target_id'])
      ->condition('eu.source_type', $entityType)
      ->condition('eu.source_id', $entityId)
      ->condition('eu.target_type', 'file')
      ->execute()
      ->fetchCol();
    foreach ($targets as $fid) {
      if (is_numeric($fid)) {
        $this->enqueue((int) $fid);
      }
    }
  }

  /**
   * Determines whether formatted text changed between entity versions.
   */
  private function formattedTextChanged(EntityInterface $original, EntityInterface $current): bool {
    foreach ($current->getFieldDefinitions() as $name => $definition) {
      if (!in_array($definition->getType(), ['text', 'text_long', 'text_with_summary'], TRUE)
        || !$current->hasField($name) || !$original->hasField($name)) {
        continue;
      }
      if ($original->get($name)->getValue() !== $current->get($name)->getValue()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Finds File IDs directly referenced by an entity's fields.
   *
   * @return int[]
   *   Unique File IDs.
   */
  private function fieldFileIds(EntityInterface $entity): array {
    if (!method_exists($entity, 'getFieldDefinitions')) {
      return [];
    }
    $ids = [];
    foreach ($entity->getFieldDefinitions() as $name => $definition) {
      $type = $definition->getType();
      $isFileField = in_array($type, ['image', 'file'], TRUE)
        || ($type === 'entity_reference' && $definition->getSetting('target_type') === 'file');
      if (!$isFileField || !$entity->hasField($name)) {
        continue;
      }
      foreach ($entity->get($name)->getValue() as $item) {
        if (!empty($item['target_id']) && is_numeric($item['target_id'])) {
          $ids[] = (int) $item['target_id'];
        }
      }
    }
    return array_values(array_unique($ids));
  }

  /**
   * Removes expired temporary images in a bounded batch.
   */
  private function deleteExpiredTemporaryImages(int $limit): int {
    $cutoff = (int) $this->time->getRequestTime() - $this->temporaryAge();
    $query = $this->database->select('file_managed', 'f')
      ->fields('f', ['fid'])
      ->condition('f.filemime', 'image/%', 'LIKE')
      ->condition('f.status', 0)
      ->condition('f.created', $cutoff, '<=')
      ->condition('f.fid', $this->database->select('file_usage', 'fu')->fields('fu', ['fid']), 'NOT IN')
      ->condition('f.fid', $this->database->select('entity_usage', 'eu')
        ->fields('eu', ['target_id'])
        ->condition('eu.target_type', 'file'), 'NOT IN')
      ->orderBy('f.created', 'ASC')
      ->range(0, $limit);
    $deleted = 0;
    foreach ($query->execute()->fetchCol() as $fid) {
      $file = $this->loadImage((int) $fid);
      if ($file !== NULL && $file->isTemporary() && $this->removeFile($file, TRUE)) {
        $deleted++;
      }
    }
    return $deleted;
  }

  /**
   * Loads a File only when it is an image.
   */
  private function loadImage(int $fid): ?FileInterface {
    $file = $fid > 0 ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
    return $file instanceof FileInterface && str_starts_with((string) $file->getMimeType(), 'image/') ? $file : NULL;
  }

  /**
   * Delegates to Portal cleanup when available, preserving standalone use. */
  private function removeFile(FileInterface $file, bool $allowTemporary = FALSE): bool {
    if ($this->unusedFileCleaner !== NULL) {
      return $this->unusedFileCleaner->removeIfUnused($file, $allowTemporary) === FileCleanupStatus::Deleted;
    }
    return $this->fileRemover->delete($file, 'imagem substituída ou removida de entidade');
  }

}
