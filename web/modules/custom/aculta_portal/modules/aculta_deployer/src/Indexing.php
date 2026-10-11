<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Resultado neutro da verificação de indexação (`robots`), lido pelo painel do Portal.
 *
 * Contém só URLs públicas, estados e o valor público do cabeçalho X-Robots-Tag. Nunca contém
 * credenciais, tokens ou corpo de página.
 */
final class Indexing {

  public const FILE = 'indexing.json';

  /**
   * @param array<int, array{kind: string, url: string, ok: bool, observed: string}> $checks
   * @return array<string, mixed>
   */
  public static function build(string $environment, array $checks, bool $ok, string $checkedAt): array {
    return [
      'schema' => 1,
      'environment' => $environment,
      'checked_at' => $checkedAt,
      'ok' => $ok,
      'checks' => array_values($checks),
    ];
  }

  /**
   * Resumo para o relatório de status. Retorna null quando o arquivo não existe ou não é do schema 1.
   *
   * @param mixed $data conteúdo decodificado de indexing.json
   * @return array{environment: string, checked_at: string, ok: bool, passed: int, failed: int, failed_urls: list<string>}|null
   */
  public static function summarize(mixed $data): ?array {
    if (!is_array($data) || ($data['schema'] ?? null) !== 1) {
      return null;
    }
    $checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];
    $failed = [];
    $passed = 0;
    foreach ($checks as $check) {
      if (is_array($check) && ($check['ok'] ?? false) === true) {
        $passed++;
        continue;
      }
      $failed[] = (string) (is_array($check) ? ($check['url'] ?? '') : '');
    }
    return [
      'environment' => (string) ($data['environment'] ?? ''),
      'checked_at' => (string) ($data['checked_at'] ?? ''),
      'ok' => ($data['ok'] ?? false) === true,
      'passed' => $passed,
      'failed' => count($failed),
      'failed_urls' => $failed,
    ];
  }

}
