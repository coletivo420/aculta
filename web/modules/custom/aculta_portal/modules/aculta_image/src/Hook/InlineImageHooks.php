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
 * Configurable processing occurs after upload in inline-images.
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
   * Processes eligible files using the selected backend and settings.
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
    $this->compressor->processFile($file, $caminho);
  }

}
