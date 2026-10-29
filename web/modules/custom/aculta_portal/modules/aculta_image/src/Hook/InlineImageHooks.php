<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Hook;

use Drupal\aculta_image\Compression\ImageCompressor;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Comprime imagens enviadas no texto dos editores.
 *
 * A compressão sem perda ocorre após o upload em inline-images.
 */
final class InlineImageHooks {

  /**
   * Diretório dos uploads de imagem feitos pelos editores de texto. */
  public const DIRETORIO = 'public://inline-images/';

  public function __construct(
    #[Autowire(service: 'aculta_image.compressor')]
    private readonly ImageCompressor $compressor,
    #[Autowire(service: 'file_system')]
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Implements hook_file_insert().
   *
   * Reduz a largura para 1200 px e comprime sem perda.
   */
  #[Hook('file_insert')]
  public function fileInsert(FileInterface $file): void {
    $uri = $file->getFileUri();
    if (!str_starts_with($uri, self::DIRETORIO)) {
      return;
    }
    $caminho = $this->fileSystem->realpath($uri);
    if ($caminho === FALSE) {
      return;
    }
    $mime = (string) $file->getMimeType();
    $redimensionou = $this->compressor->redimensionar($caminho, $mime);
    $comprimiu = $this->compressor->comprimir($caminho, $mime);
    if (!$redimensionou && !$comprimiu) {
      return;
    }
    clearstatcache(TRUE, $caminho);
    $file->setSize((int) filesize($caminho));
    $file->save();
  }

}
