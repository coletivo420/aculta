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

  /**
   * Largura máxima, em px, das imagens do texto. Imagens maiores são reduzidas para esta largura (aceita e converte);
   * imagens menores não mudam. Substitui a validação de dimensão do core, que recusava o upload.
   */
  public const LARGURA_MAXIMA = 1200;

  /** Tipos que a redução de largura aceita. */
  private const TIPOS_REDIMENSIONAVEIS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  public function __construct(private readonly LoggerChannelFactoryInterface $loggerFactory) {}

  /**
   * Comando de redução de largura (só reduz, mantém a proporção). Pura: testável sem executar nada.
   *
   * @return string[]
   */
  public static function redimensionamento(string $caminho, int $largura = self::LARGURA_MAXIMA): array {
    return ['mogrify', '-resize', $largura . 'x>', $caminho];
  }

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

  /** Reduz $caminho para LARGURA_MAXIMA in place, se for maior. Retorna TRUE se o processo terminou com sucesso. */
  public function redimensionar(string $caminho, string $mime): bool {
    return $this->executar(in_array($mime, self::TIPOS_REDIMENSIONAVEIS, TRUE) && is_file($caminho) ? self::redimensionamento($caminho) : NULL, $caminho);
  }

  /** Comprime $caminho in place. Retorna TRUE se o processo terminou com sucesso. */
  public function comprimir(string $caminho, string $mime): bool {
    return $this->executar(is_file($caminho) ? self::comando($mime, $caminho) : NULL, $caminho);
  }

  /** Executa um comando fixo de otimização. NULL ou falha retornam FALSE e não alteram o arquivo original. */
  private function executar(?array $comando, string $caminho): bool {
    if ($comando === NULL) {
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
