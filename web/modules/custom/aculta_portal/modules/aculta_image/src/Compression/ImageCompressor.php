<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Compression;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileInterface;

/**
 * Applies the configured PHP image backend to editor inline images.
 */
final class ImageCompressor {

  /**
   * Configuration defaults also used before the first settings save.
   */
  private const DEFAULTS = [
    'backend' => 'imagick',
    'compression_enabled' => TRUE,
    'max_width' => 1200,
    'supported_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'webp_enabled' => TRUE,
    'webp_quality' => 82,
    'output_format' => 'original',
    'jpeg_quality' => 82,
    'png_compression' => 6,
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Processes an inline image and updates file metadata after atomic replace.
   */
  public function processFile(FileInterface $file, string $path): bool {
    $config = $this->settings();
    $mime = (string) $file->getMimeType();
    if (!$config['compression_enabled'] || !in_array($mime, $config['supported_mime_types'], TRUE) || !is_file($path)) {
      return FALSE;
    }
    $backend = $config['backend'];
    if ($backend === 'imagick' && !class_exists(\Imagick::class)) {
      return FALSE;
    }
    if ($backend === 'gd' && !extension_loaded('gd')) {
      return FALSE;
    }
    try {
      $result = $backend === 'imagick'
        ? $this->processImagick($path, $mime, $config)
        : $this->processGd($path, $mime, $config);
      if ($result === NULL) {
        return FALSE;
      }
      $old_uri = $file->getFileUri();
      $new_path = $path;
      if ($result['mime'] !== $mime) {
        $new_path = preg_replace('/\.[^.]+$/', '.' . $this->extensionForMime($result['mime']), $path)
          ?: $path . '.' . $this->extensionForMime($result['mime']);
        if (file_exists($new_path) && $new_path !== $path) {
          throw new \RuntimeException('O destino WebP já existe.');
        }
      }
      $backup = NULL;
      if ($new_path === $path) {
        $backup = $path . '.aculta-backup-' . bin2hex(random_bytes(4));
        if (!copy($path, $backup)) {
          throw new \RuntimeException('Não foi possível proteger o original antes da substituição.');
        }
      }
      if (!rename($result['temporary'], $new_path)) {
        if ($backup !== NULL) {
          @unlink($backup);
        }
        throw new \RuntimeException('Não foi possível substituir o arquivo processado.');
      }
      if ($new_path !== $path) {
        $new_uri = preg_replace('/\.[^.]+$/', '.' . $this->extensionForMime($result['mime']), $old_uri)
          ?: $old_uri . '.' . $this->extensionForMime($result['mime']);
        $file->setFileUri($new_uri);
        $file->setFilename(basename($new_uri));
        $file->setMimeType($result['mime']);
      }
      clearstatcache(TRUE, $new_path);
      $file->setSize((int) filesize($new_path));
      $file->save();
      if ($new_path !== $path && !unlink($path)) {
        $this->loggerFactory->get('aculta_image')->warning(
          'A imagem {arquivo} foi convertida, mas o original não pôde ser removido.',
          ['arquivo' => basename($path)],
        );
      }
      if ($backup !== NULL) {
        @unlink($backup);
      }
      return TRUE;
    }
    catch (\Throwable $exception) {
      if (isset($backup) && is_string($backup) && is_file($backup)) {
        @rename($backup, $path);
      }
      if (isset($new_path) && $new_path !== $path && is_file($new_path)) {
        @unlink($new_path);
        if (isset($old_uri)) {
          $file->setFileUri($old_uri);
          $file->setFilename(basename($old_uri));
          $file->setMimeType($mime);
        }
      }
      if (isset($result['temporary']) && is_file($result['temporary'])) {
        @unlink($result['temporary']);
      }
      $this->loggerFactory->get('aculta_image')->warning(
        'Processamento da imagem {arquivo} falhou: {erro}',
        ['arquivo' => basename($path), 'erro' => $exception->getMessage()],
      );
      return FALSE;
    }
  }

  /**
   * Returns the effective settings including defensive defaults.
   *
   * @return array<string, mixed>
   *   Image processing settings.
   */
  private function settings(): array {
    return array_replace(self::DEFAULTS, $this->configFactory->get('aculta_image.settings')->getRawData());
  }

  /**
   * Processes a file with Imagick, preserving all frames for non-conversion.
   *
   * @return array{temporary: string, mime: string}|null
   *   Temporary output/MIME or NULL when unsupported.
   */
  private function processImagick(string $path, string $mime, array $config): ?array {
    $image = new \Imagick();
    try {
      $image->readImage($path);
      $animated = $image->getNumberImages() > 1;
      $target_mime = $this->targetMime($mime, $config, $animated);
      $max_width = (int) $config['max_width'];
      foreach ($image as $frame) {
        if ($frame->getImageWidth() > $max_width) {
          $frame->thumbnailImage($max_width, 0);
        }
        $frame->stripImage();
        $frame->setImageFormat($this->formatForMime($target_mime));
        if (in_array($target_mime, ['image/jpeg', 'image/webp'], TRUE)) {
          $frame->setImageCompressionQuality($target_mime === 'image/webp' ? (int) $config['webp_quality'] : (int) $config['jpeg_quality']);
        }
        elseif ($target_mime === 'image/png') {
          $frame->setOption('png:compression-level', (string) $config['png_compression']);
        }
      }
      $temporary = tempnam(dirname($path), '.aculta-image-');
      if ($temporary === FALSE) {
        throw new \RuntimeException('Não foi possível criar arquivo temporário.');
      }
      $image->writeImages($temporary, $animated);
      return ['temporary' => $temporary, 'mime' => $target_mime];
    }
    finally {
      $image->clear();
      $image->destroy();
    }
  }

  /**
   * Processes a static image with GD. GIF is skipped to preserve animation.
   *
   * @return array{temporary: string, mime: string}|null
   *   The temporary output and resulting MIME, or NULL if unsupported.
   */
  private function processGd(string $path, string $mime, array $config): ?array {
    if ($mime === 'image/gif') {
      return NULL;
    }
    $bytes = file_get_contents($path);
    $image = $bytes === FALSE ? FALSE : @imagecreatefromstring($bytes);
    if ($image === FALSE) {
      return NULL;
    }
    try {
      $width = imagesx($image);
      $height = imagesy($image);
      $max_width = (int) $config['max_width'];
      if ($width > $max_width) {
        $target_height = (int) round($height * $max_width / $width);
        $resized = imagecreatetruecolor($max_width, $target_height);
        imagealphablending($resized, FALSE);
        imagesavealpha($resized, TRUE);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $max_width, $target_height, $width, $height);
        imagedestroy($image);
        $image = $resized;
      }
      $target_mime = $this->targetMime($mime, $config, FALSE);
      $temporary = tempnam(dirname($path), '.aculta-image-');
      if ($temporary === FALSE) {
        throw new \RuntimeException('Não foi possível criar arquivo temporário.');
      }
      $success = match ($target_mime) {
        'image/jpeg' => imagejpeg($image, $temporary, (int) $config['jpeg_quality']),
        'image/png' => imagepng($image, $temporary, (int) $config['png_compression']),
        'image/webp' => function_exists('imagewebp') && imagewebp($image, $temporary, (int) $config['webp_quality']),
        default => FALSE,
      };
      if (!$success) {
        @unlink($temporary);
        return NULL;
      }
      return ['temporary' => $temporary, 'mime' => $target_mime];
    }
    finally {
      imagedestroy($image);
    }
  }

  /**
   * Maps an image MIME type to the explicit Imagick format name.
   */
  private function formatForMime(string $mime): string {
    return match ($mime) {
      'image/jpeg' => 'JPEG',
      'image/png' => 'PNG',
      'image/gif' => 'GIF',
      'image/webp' => 'WEBP',
      default => throw new \InvalidArgumentException('Formato de imagem não permitido.'),
    };
  }

  /**
   * Determines a safe output MIME, keeping animated GIFs in their source form.
   */
  private function targetMime(string $source_mime, array $config, bool $animated): string {
    if ($animated && $source_mime === 'image/gif') {
      return $source_mime;
    }
    if (!empty($config['webp_enabled']) && $source_mime !== 'image/webp') {
      return 'image/webp';
    }
    $output = (string) ($config['output_format'] ?? 'original');
    return in_array($output, ['image/jpeg', 'image/png'], TRUE) ? $output : $source_mime;
  }

  /**
   * Returns the canonical extension for an image MIME type.
   */
  private function extensionForMime(string $mime): string {
    return match ($mime) {
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/gif' => 'gif',
      'image/webp' => 'webp',
      default => throw new \InvalidArgumentException('Formato de imagem não permitido.'),
    };
  }

}
