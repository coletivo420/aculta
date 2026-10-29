<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Compression;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\Process\Process;

/**
 * Compresses inline images without loss when supported tools are available.
 *
 * Uses fixed arguments for optipng, jpegoptim, and gifsicle. If a tool is
 * missing or fails, the original file remains unchanged.
 */
final class ImageCompressor {

  /**
   * Tempo máximo, em segundos, por arquivo. */
  public const TIMEOUT = 60;

  /**
   * Maximum width for inline images.
   *
   * Larger images are resized; smaller images remain unchanged. This replaces
   * Core dimension validation, which rejected oversized uploads.
   */
  public const LARGURA_MAXIMA = 1200;

  /**
   * Tipos que a redução de largura aceita. */
  private const TIPOS_REDIMENSIONAVEIS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  public function __construct(private readonly LoggerChannelFactoryInterface $loggerFactory) {}

  /**
   * Builds the fixed command that reduces an image without changing its ratio.
   *
   * @param string $caminho
   *   Image path.
   * @param int $largura
   *   Maximum width.
   *
   * @return string[]
   *   Command and arguments.
   */
  public static function redimensionamento(string $caminho, int $largura = self::LARGURA_MAXIMA): array {
    return ['mogrify', '-resize', $largura . 'x>', $caminho];
  }

  /**
   * Builds a fixed lossless compression command for a MIME type.
   *
   * @param string $mime
   *   Image MIME type.
   * @param string $caminho
   *   Image path.
   *
   * @return string[]|null
   *   Command and arguments, or NULL when the type is not supported.
   */
  public static function comando(string $mime, string $caminho): ?array {
    return match ($mime) {
      'image/jpeg' => [
        'jpegoptim', '--strip-all', '--all-progressive', '-q', $caminho,
      ],
      'image/png' => [
        'optipng', '-o2', '-quiet', $caminho,
      ],
      'image/gif' => [
        'gifsicle', '-O2', '--batch', $caminho,
      ],
      default => NULL,
    };
  }

  /**
   * Reduces the image in place when it exceeds the maximum width.
   *
   * @param string $caminho
   *   Image path.
   * @param string $mime
   *   Image MIME type.
   *
   * @return bool
   *   TRUE when the process succeeds.
   */
  public function redimensionar(string $caminho, string $mime): bool {
    return $this->executar(
      in_array($mime, self::TIPOS_REDIMENSIONAVEIS, TRUE) && is_file($caminho)
        ? self::redimensionamento($caminho)
        : NULL,
      $caminho,
    );
  }

  /**
   * Compresses the image in place when its MIME type is supported.
   *
   * @param string $caminho
   *   Image path.
   * @param string $mime
   *   Image MIME type.
   *
   * @return bool
   *   TRUE when the process succeeds.
   */
  public function comprimir(string $caminho, string $mime): bool {
    return $this->executar(is_file($caminho) ? self::comando($mime, $caminho) : NULL, $caminho);
  }

  /**
   * Executes a fixed optimization command.
   *
   * @param array<int, string>|null $comando
   *   Command and arguments, or NULL when unavailable.
   * @param string $caminho
   *   Image path used for logging.
   *
   * @return bool
   *   TRUE on success; FALSE leaves the original unchanged.
   */
  private function executar(?array $comando, string $caminho): bool {
    if ($comando === NULL) {
      return FALSE;
    }
    try {
      $processo = new Process($comando);
      $processo->setTimeout(self::TIMEOUT);
      $processo->run();
      if (!$processo->isSuccessful()) {
        $this->loggerFactory->get('aculta_image')->warning(
          'Compressão falhou para {arquivo}: {erro}',
          [
            'arquivo' => basename($caminho),
            'erro' => trim($processo->getErrorOutput()),
          ],
        );
        return FALSE;
      }
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('aculta_image')->warning(
        'Compressão indisponível para {arquivo}: {erro}',
        [
          'arquivo' => basename($caminho),
          'erro' => $e->getMessage(),
        ],
      );
      return FALSE;
    }
    return TRUE;
  }

}
