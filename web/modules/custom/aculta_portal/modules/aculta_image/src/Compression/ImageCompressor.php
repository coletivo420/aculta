<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Compression;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\Process\Process;

/**
 * Compressão sem perda das imagens enviadas no texto (aculta_image, código próprio).
 *
 * Usa somente os binários documentados pela Hostinger para otimização (optipng, jpegoptim e gifsicle), com argumentos
 * fixos e sem entrada do usuário além do caminho do arquivo. Sem binário, ou em caso de falha, o arquivo original
 * permanece como foi enviado.
 */
final class ImageCompressor {

  /** Tempo máximo, em segundos, por arquivo. */
  public const TIMEOUT = 60;

  public function __construct(private readonly LoggerChannelFactoryInterface $loggerFactory) {}

  /**
   * Comando de compressão sem perda para o tipo MIME, ou NULL se o tipo não é comprimido.
   * Pura: testável sem executar nada.
   *
   * @return string[]|null
   */
  public static function comando(string $mime, string $caminho): ?array {
    return match ($mime) {
      'image/jpeg' => ['jpegoptim', '--strip-all', '--all-progressive', '-q', $caminho],
      'image/png' => ['optipng', '-o2', '-quiet', $caminho],
      'image/gif' => ['gifsicle', '-O2', '--batch', $caminho],
      default => NULL,
    };
  }

  /** Comprime $caminho in place. Retorna TRUE se o processo terminou com sucesso. */
  public function comprimir(string $caminho, string $mime): bool {
    $comando = self::comando($mime, $caminho);
    if ($comando === NULL || !is_file($caminho)) {
      return FALSE;
    }
    try {
      $processo = new Process($comando);
      $processo->setTimeout(self::TIMEOUT);
      $processo->run();
      if (!$processo->isSuccessful()) {
        $this->loggerFactory->get('aculta_image')->warning('Compressão falhou para {arquivo}: {erro}', ['arquivo' => basename($caminho), 'erro' => trim($processo->getErrorOutput())]);
        return FALSE;
      }
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('aculta_image')->warning('Compressão indisponível para {arquivo}: {erro}', ['arquivo' => basename($caminho), 'erro' => $e->getMessage()]);
      return FALSE;
    }
    return TRUE;
  }

}
