<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Hook;

use Drupal\aculta_image\Compression\ImageCompressor;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Imagens enviadas no texto dos editores (diretório inline-images): comprime sem perda logo após o upload.
 */
final class InlineImageHooks {

  /** Diretório dos uploads de imagem feitos pelos editores de texto. */
  public const DIRETORIO = 'public://inline-images/';

  public function __construct(
    #[Autowire(service: 'aculta_image.compressor')]
    private readonly ImageCompressor $compressor,
    #[Autowire(service: 'file_system')]
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /** Implements hook_file_insert(). */
  #[Hook('file_insert')]
  public function fileInsert(FileInterface $file): void {
    $uri = $file->getFileUri();
    if (!str_starts_with($uri, self::DIRETORIO)) {
      return;
    }
    $caminho = $this->fileSystem->realpath($uri);
    if ($caminho === FALSE || !$this->compressor->comprimir($caminho, (string) $file->getMimeType())) {
      return;
    }
    clearstatcache(TRUE, $caminho);
    $file->setSize((int) filesize($caminho));
    $file->save();
  }

}
