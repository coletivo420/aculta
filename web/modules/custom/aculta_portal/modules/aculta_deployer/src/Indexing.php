<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Resultado neutro da verificação de indexação.
 *
 * O painel do Portal consome somente URLs públicas, estados e o cabeçalho
 * X-Robots-Tag. O resultado não contém credenciais, tokens ou corpo de página.
 */
final class Indexing {

  public const FILE = 'indexing.json';

  /**
   * Builds a schema 1 indexing report.
   *
   * @param string $environment
   *   Environment checked by the report.
   * @param array<int, array{kind: string, url: string, ok: bool, observed: string}> $checks
   *   Individual checks.
   * @param bool $ok
   *   Whether all checks passed.
   * @param string $checkedAt
   *   Timestamp of the check.
   *
   * @return array<string, mixed>
   *   Report data safe for the Portal to read.
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
   * Summarizes a schema 1 report for the status page.
   *
   * @param mixed $data
   *   Decoded data from indexing.json.
   *
   * @return array{environment: string, checked_at: string, ok: bool, passed: int, failed: int, failed_urls: list<string>}|null
   *   Summary, or NULL when the input is not a schema 1 report.
   */
  public static function summarize(mixed $data): ?array {
    if (!is_array($data) || ($data['schema'] ?? NULL) !== 1) {
      return NULL;
    }
    $checks = is_array($data['checks'] ?? NULL) ? $data['checks'] : [];
    $failed = [];
    $passed = 0;
    foreach ($checks as $check) {
      if (is_array($check) && ($check['ok'] ?? FALSE) === TRUE) {
        $passed++;
        continue;
      }
      $failed[] = (string) (is_array($check) ? ($check['url'] ?? '') : '');
    }
    return [
      'environment' => (string) ($data['environment'] ?? ''),
      'checked_at' => (string) ($data['checked_at'] ?? ''),
      'ok' => ($data['ok'] ?? FALSE) === TRUE,
      'passed' => $passed,
      'failed' => count($failed),
      'failed_urls' => $failed,
    ];
  }

}
