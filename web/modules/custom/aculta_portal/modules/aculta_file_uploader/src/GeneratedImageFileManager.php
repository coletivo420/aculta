<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

/**
 * Registra derivados de imagem gerados como arquivos temporários.
 *
 * Movido do aculta_image (0.1.0-dev.48). Lê dimensões pelo ImageFactory do Core
 * para não depender do aculta_image, que já depende deste módulo.
 */
final class GeneratedImageFileManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly MimeTypeGuesser $mimeTypeGuesser,
    private readonly ImageFactory $imageFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Registra uma imagem pública gerada como temporária.
   *
   * Retorna NULL se a imagem for inválida.
   * Derivados temporários não têm entity usage e saem na limpeza normal após
   * system.file:temporary_maximum_age.
   */
  public function registerTemporaryDerivative(string $uri): ?FileInterface {
    if (!str_starts_with($uri, 'public://')) {
      return NULL;
    }
    $realpath = $this->fileSystem->realpath($uri);
    if (!is_string($realpath) || !is_file($realpath) || !is_readable($realpath)) {
      return NULL;
    }
    if (!$this->hasDimensions($uri)) {
      return NULL;
    }
    $mime = $this->mimeTypeGuesser->guessMimeType($uri);
    if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
      return NULL;
    }

    $size = filesize($realpath);
    if ($size === FALSE || $size < 1) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('file');
    $existing = $storage->loadByProperties(['uri' => $uri]);
    $file = reset($existing);
    if ($file instanceof FileInterface) {
      // Um arquivo permanente pode ter referências independentes; nunca rebaixar.
      if ($file->isPermanent()) {
        return $file;
      }
      // A limpeza seleciona por `created`: a vida útil começa no primeiro
      // registro e não é renovada a cada renderização. Só um derivado trocado
      // no disco tem tamanho e status atualizados.
      if ((int) $file->getSize() !== $size || !$file->isTemporary()) {
        $file->setTemporary();
        $file->setSize($size);
        $file->save();
      }
      return $file;
    }

    $file = File::create([
      'uid' => 0,
      'filename' => basename($uri),
      'uri' => $uri,
      'filemime' => $mime,
      'filesize' => $size,
      'status' => 0,
      'created' => $this->time->getRequestTime(),
      'changed' => $this->time->getRequestTime(),
    ]);
    $file->save();
    return $file;
  }

  /**
   * Confirma que o URI é uma imagem válida com dimensões positivas.
   */
  private function hasDimensions(string $uri): bool {
    $image = $this->imageFactory->get($uri);
    return $image->isValid() && $image->getWidth() > 0 && $image->getHeight() > 0;
  }

}
