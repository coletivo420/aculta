<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Exclusão controlada de imagem, com checagem de referências no momento da remoção.
 */
final class FileRemover {

  public function __construct(
    private readonly FileUsageInterface $fileUsage,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /** Apaga $file se nenhuma entidade o referencia. Retorna TRUE se foi apagado. */
  public function delete(FileInterface $file, string $motivo): bool {
    if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
      return FALSE;
    }
    if ($this->hasReferences($file)) {
      $this->loggerFactory->get('aculta_image')->notice('Exclusão recusada: arquivo {fid} ainda em uso ({motivo}).', ['fid' => $file->id(), 'motivo' => $motivo]);
      return FALSE;
    }
    // Invalida os derivados antes de remover o original; seus arquivos não são temporários.
    foreach ($this->entityTypeManager->getStorage('image_style')->loadMultiple() as $style) {
      $style->flush($file->getFileUri());
    }
    $fid = (int) $file->id();
    $this->entityTypeManager->getStorage('file')->delete([$file]);
    foreach (['aculta_image_fingerprint', 'aculta_image_origin', 'aculta_image_quarantine'] as $table) {
      if ($this->database->schema()->tableExists($table)) {
        $this->database->delete($table)->condition('fid', $fid)->execute();
      }
    }
    $this->loggerFactory->get('aculta_image')->info('Arquivo {fid} removido: {motivo}.', ['fid' => $file->id(), 'motivo' => $motivo]);
    return TRUE;
  }

  /** Confere tanto o registro tradicional do File API quanto referências entity_usage. */
  public function hasReferences(FileInterface $file): bool {
    if (!empty($this->fileUsage->listUsage($file))) {
      return TRUE;
    }
    return (bool) $this->database->select('entity_usage', 'eu')
      ->fields('eu', ['target_id'])
      ->condition('eu.target_type', 'file')
      ->condition('eu.target_id', (string) $file->id())
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

}
