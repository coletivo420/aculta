<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher;

/**
 * Valida o formato de um unified diff antes de qualquer aplicação.
 *
 * Classe pura: sem Drupal, sistema de arquivos ou processos. Recusa diffs
 * grandes, sem cabeçalhos ou hunks, e caminhos absolutos ou fora do pacote.
 */
final class PatchDiffValidator {

  /**
   * Limite de tamanho do diff, em bytes (256 KiB).
   */
  public const MAX_BYTES = 262144;

  /**
   * Lança exceção quando o diff não tem a forma de um unified diff utilizável.
   *
   * @throws \RuntimeException
   *   Quando o diff é inválido, excede o limite ou aponta para fora do pacote.
   */
  public function assertValid(string $id, string $diff): void {
    if (strlen($diff) > self::MAX_BYTES || !str_contains($diff, '--- ') || !str_contains($diff, '+++ ') || !preg_match('/^@@/m', $diff)) {
      throw new \RuntimeException(sprintf('O conteúdo do patch %s não é um unified diff válido ou excede 256 KiB.', $id));
    }
    if ($this->escapesPackage($diff)) {
      throw new \RuntimeException(sprintf('O patch %s aponta para um caminho fora do pacote.', $id));
    }
  }

  /**
   * Indica se um cabeçalho usa caminho absoluto, letra de unidade ou `..`.
   *
   * `/dev/null` é aceito para criação e remoção de arquivos.
   */
  public function escapesPackage(string $diff): bool {
    $absolute = '~^(?:---|\+\+\+)\s+(?!(?:/dev/null)(?:\s|$))(?:/|[A-Za-z]:[\\\\/])|^(?:---|\+\+\+)\s+.*(?:^|/)\.\.(?:/|$)~m';
    return (bool) preg_match($absolute, $diff);
  }

}
