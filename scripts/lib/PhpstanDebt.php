<?php

declare(strict_types=1);

/**
 * @file
 * Shared parsing, classification and comparison of the PHPStan baseline.
 *
 * The baseline is the single frozen record of historical PHPStan level 10
 * debt. Gates compare it entry by entry so a total-count change cannot hide
 * new debt transferred between files or identifiers.
 */
final class PhpstanDebt {

  /**
   * Parses a PHPStan NEON baseline.
   *
   * NEON is not a runtime dependency of this project (PHPStan ships its own
   * parser), so this reads the subset PHPStan writes: one indented sequence
   * of mappings with scalar values and `'''` heredocs for multi-line messages.
   *
   * @return list<array{path: string, identifier: string, message: string, count: int, valid: bool, error: string}>
   *   Parsed entries in file order.
   */
  public static function parseBaseline(string $contents): array {
    $lines = preg_split('/\R/', $contents);
    if ($lines === FALSE) {
      throw new \RuntimeException('não foi possível dividir o baseline em linhas.');
    }
    $lineCount = count($lines);
    $entries = [];
    $index = 0;
    while ($index < $lineCount) {
      if (!self::isEntryStart($lines[$index])) {
        $index++;
        continue;
      }
      $index++;
      $fields = ['path' => '', 'identifier' => '', 'rawMessage' => '', 'message' => '', 'count' => 0];
      while ($index < $lineCount
        && !self::isEntryStart($lines[$index])
        && $lines[$index] !== "\t\t"
        && trim($lines[$index]) !== '') {
        if (preg_match('/^\t\t\t([A-Za-z_]+): ?(.*)$/', $lines[$index], $match) !== 1) {
          $index++;
          continue;
        }
        $field = $match[1];
        $value = $match[2];
        if ($value === "'''") {
          [$value, $index] = self::readHeredoc($lines, $index + 1);
          $fields[$field] = $value;
          continue;
        }
        $fields[$field] = $value;
        $index++;
      }
      $message = $fields['rawMessage'] !== '' ? $fields['rawMessage'] : $fields['message'];
      $message = self::decodeScalar($message);
      $identifier = self::decodeScalar($fields['identifier']);
      $path = self::decodeScalar($fields['path']);
      $count = (int) self::decodeScalar((string) $fields['count']);
      $error = '';
      if ($path === '') {
        $error = 'entrada sem path';
      }
      elseif (str_starts_with($path, '/') || str_contains($path, '..')) {
        $error = 'path deve ser relativo ao repositório';
      }
      elseif ($count < 1) {
        $error = 'count deve ser inteiro positivo';
      }
      elseif ($message === '') {
        $error = 'entrada sem mensagem';
      }
      $entries[] = [
        'path' => $path,
        'identifier' => $identifier,
        'message' => $message,
        'count' => $count,
        'valid' => $error === '',
        'error' => $error,
      ];
    }
    return $entries;
  }

  /**
   * Returns a stable identity for one baseline entry.
   *
   * @param array{path: string, identifier: string, message: string, count: int, valid?: bool, error?: string} $entry
   *   Parsed entry.
   */
  public static function key(array $entry): string {
    return $entry['path'] . "\0" . $entry['identifier'] . "\0" . self::normalizeMessage($entry['message']);
  }

  /**
   * Collapses insignificant whitespace so message formatting changes do not
   * fabricate a new error identity.
   */
  public static function normalizeMessage(string $message): string {
    return trim((string) preg_replace('/\s+/u', ' ', $message));
  }

  /**
   * Maps a file path to the owning module, theme or script group.
   */
  public static function module(string $path): string {
    if (preg_match('#^web/modules/custom/aculta_portal/modules/([^/]+)/#', $path, $match) === 1) {
      return $match[1];
    }
    if (str_starts_with($path, 'web/modules/custom/aculta_portal/')) {
      return 'aculta_portal';
    }
    if (preg_match('#^web/modules/custom/([^/]+)/#', $path, $match) === 1) {
      return $match[1];
    }
    if (preg_match('#^web/themes/custom/([^/]+)/#', $path, $match) === 1) {
      return $match[1];
    }
    if (str_starts_with($path, 'scripts/')) {
      return 'scripts';
    }
    return 'outros';
  }

  /**
   * Whether the path belongs to a test suite.
   */
  public static function isTest(string $path): bool {
    return preg_match('#(^|/)tests?/#', $path) === 1
      || str_ends_with($path, 'Test.php');
  }

  /**
   * Functional criticality, based on context instead of identifier alone.
   */
  public static function criticality(string $identifier, string $path, string $message = ''): string {
    $sensitivePath = (bool) preg_match(
      '#pagament|mercadopago|checkout|commerce|donation|access|permission|auth|login|user|csrf|seguranca|security#i',
      $path,
    );
    $sensitiveIdentifier = (bool) preg_match(
      '#(^|\.)(access|permission|sql|xss|csrf|security)#i',
      $identifier,
    );
    $sensitiveMessage = (bool) preg_match(
      '#pagament|permiss|acesso|autoriz|token|senha|credencial|csrf|xss|sql#i',
      $message,
    );
    if (($sensitivePath && $sensitiveIdentifier) || $sensitiveIdentifier || ($sensitivePath && $sensitiveMessage)) {
      return 'alta';
    }
    if (str_starts_with($identifier, 'missingType.')
      || in_array($identifier, ['deprecation', 'unused', 'variable.unused'], TRUE)
      || str_starts_with($identifier, 'unused')) {
      return 'baixa';
    }
    return 'media';
  }

  /**
   * Aggregates occurrence counts by key, rejecting contradictory duplicates.
   *
   * @param list<array{path: string, identifier: string, message: string, count: int, valid?: bool, error?: string}> $entries
   *   Parsed entries.
   *
   * @return array<string, int>
   *   Occurrences by entry key.
   */
  public static function countsByKey(array $entries): array {
    $counts = [];
    foreach ($entries as $entry) {
      $key = self::key($entry);
      $counts[$key] = ($counts[$key] ?? 0) + $entry['count'];
    }
    return $counts;
  }

  /**
   * @param list<array{path: string, identifier: string, message: string, count: int, valid?: bool, error?: string}> $entries
   *   Parsed entries.
   *
   * @return array<string, int>
   *   Occurrences by module.
   */
  public static function countsByModule(array $entries): array {
    $counts = [];
    foreach ($entries as $entry) {
      $module = self::module($entry['path']);
      $counts[$module] = ($counts[$module] ?? 0) + $entry['count'];
    }
    arsort($counts);
    return $counts;
  }

  /**
   * @param list<array{path: string, identifier: string, message: string, count: int, valid?: bool, error?: string}> $entries
   *   Parsed entries.
   *
   * @return array<string, int>
   *   Occurrences by identifier.
   */
  public static function countsByIdentifier(array $entries): array {
    $counts = [];
    foreach ($entries as $entry) {
      $identifier = $entry['identifier'] !== '' ? $entry['identifier'] : 'sem.identificador';
      $counts[$identifier] = ($counts[$identifier] ?? 0) + $entry['count'];
    }
    arsort($counts);
    return $counts;
  }

  /**
   * @param list<array{path: string, identifier: string, message: string, count: int, valid?: bool, error?: string}> $entries
   *   Parsed entries.
   *
   * @return array{producao: int, testes: int}
   *   Frozen occurrences in production and tests.
   */
  public static function splitProductionTests(array $entries): array {
    $split = ['producao' => 0, 'testes' => 0];
    foreach ($entries as $entry) {
      $split[self::isTest($entry['path']) ? 'testes' : 'producao'] += $entry['count'];
    }
    return $split;
  }

  /**
   * Compares a reference baseline with the current one.
   *
   * @param list<array{path: string, identifier: string, message: string, count: int, valid?: bool, error?: string}> $reference
   *   Frozen reference entries.
   * @param list<array{path: string, identifier: string, message: string, count: int, valid?: bool, error?: string}> $current
   *   Current entries.
   *
   * @return array{removed: list<string>, kept: list<string>, added: list<string>, increased: list<array{key: string, reference: int, current: int}>, decreased: list<array{key: string, reference: int, current: int}>, modified: list<string>}
   *   Classification of the evolution.
   */
  public static function compare(array $reference, array $current): array {
    $referenceCounts = self::countsByKey($reference);
    $currentCounts = self::countsByKey($current);
    $removed = [];
    $kept = [];
    $added = [];
    $increased = [];
    $decreased = [];
    $modified = [];
    foreach ($referenceCounts as $key => $count) {
      if (!isset($currentCounts[$key])) {
        $removed[] = $key;
        continue;
      }
      $kept[] = $key;
      if ($currentCounts[$key] > $count) {
        $increased[] = ['key' => $key, 'reference' => $count, 'current' => $currentCounts[$key]];
      }
      elseif ($currentCounts[$key] < $count) {
        $decreased[] = ['key' => $key, 'reference' => $count, 'current' => $currentCounts[$key]];
      }
    }
    foreach ($currentCounts as $key => $count) {
      if (!isset($referenceCounts[$key])) {
        $added[] = $key;
      }
    }

    // A key that disappears while another appears for the same
    // path+identifier is an identity change that needs human review.
    $referenceGroups = self::groupByIdentity($referenceCounts);
    $currentGroups = self::groupByIdentity($currentCounts);
    $matchedRemoved = [];
    foreach ($removed as $position => $key) {
      [$path, $identifier] = explode("\0", $key, 3);
      $group = $path . "\0" . $identifier;
      if (!isset($currentGroups[$group])) {
        continue;
      }
      foreach ($referenceGroups[$group] ?? [] as $referenceKey) {
        if (!isset($currentCounts[$referenceKey])) {
          foreach ($currentGroups[$group] as $currentKey) {
            if (!isset($referenceCounts[$currentKey]) && !in_array($currentKey, $modified, TRUE)) {
              $modified[] = $referenceKey;
              $modified[] = $currentKey;
              $matchedRemoved[$position] = TRUE;
              break 2;
            }
          }
        }
      }
    }
    $removed = array_values(array_filter($removed, static fn (string $key, int $position): bool => !isset($matchedRemoved[$position]), ARRAY_FILTER_USE_BOTH));
    $added = array_values(array_filter($added, static fn (string $key): bool => !in_array($key, $modified, TRUE)));

    return [
      'removed' => $removed,
      'kept' => $kept,
      'added' => $added,
      'increased' => $increased,
      'decreased' => $decreased,
      'modified' => array_values(array_unique($modified)),
    ];
  }

  /**
   * @param array<string, int> $counts
   *   Counts by entry key.
   *
   * @return array<string, list<string>>
   *   Keys grouped by path and identifier.
   */
  private static function groupByIdentity(array $counts): array {
    $groups = [];
    foreach (array_keys($counts) as $key) {
      [$path, $identifier] = explode("\0", $key, 3);
      $groups[$path . "\0" . $identifier][] = $key;
    }
    return $groups;
  }

  /**
   * @param array<int, string> $lines
   *   Baseline lines.
   * @param int $index
   *   First line after the heredoc opener.
   *
   * @return array{0: string, 1: int}
   *   Decoded value and the index of the line after the closing marker.
   */
  private static function readHeredoc(array $lines, int $index): array {
    $body = [];
    $lineCount = count($lines);
    while ($index < $lineCount && trim($lines[$index], "\t") !== "'''") {
      $body[] = $lines[$index];
      $index++;
    }
    $indentation = NULL;
    foreach ($body as $line) {
      if (trim($line) === '') {
        continue;
      }
      $tabs = strlen($line) - strlen(ltrim($line, "\t"));
      $indentation = $indentation === NULL ? $tabs : min($indentation, $tabs);
    }
    $indentation = $indentation ?? 0;
    $decoded = [];
    foreach ($body as $line) {
      $decoded[] = substr($line, $indentation);
    }
    return [implode("\n", $decoded), $index + 1];
  }

  /**
   * Decodes a scalar value written by PHPStan's NEON exporter.
   */
  private static function decodeScalar(string $value): string {
    $value = trim($value);
    if ($value === '' || $value === 'null') {
      return '';
    }
    if (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2) {
      return str_replace("''", "'", substr($value, 1, -1));
    }
    return $value;
  }

  private static function isEntryStart(string $line): bool {
    return preg_match('/^\t\t-$/', $line) === 1;
  }

}
