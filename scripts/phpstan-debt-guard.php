<?php

declare(strict_types=1);

/**
 * @file
 * Blocking gates for the frozen PHPStan level 10 debt.
 *
 * Usage:
 *   php scripts/phpstan-debt-guard.php check \
 *     --baseline=pstan-baseline.neon --config=phpstan.neon \
 *     --git-base=origin/dev --analysis=/tmp/phpstan.json
 *
 *   php scripts/phpstan-debt-guard.php diff \
 *     --baseline=phpstan-baseline.neon --git-base=origin/dev
 *
 * Exit codes: 0 = PASS, 1 = violation, 2 = infrastructure/usage failure.
 *
 * The guard never regenerates the baseline. It compares the committed
 * baseline with a reference revision and, when an analysis JSON is provided,
 * verifies that every current error is frozen and every frozen entry still
 * exists.
 */

require_once __DIR__ . '/lib/PhpstanDebt.php';

const PHPSTAN_GUARD_CANONICAL_BASELINE = 'phpstan-baseline.neon';
const PHPSTAN_GUARD_REQUIRED_LEVEL = 10;
const PHPSTAN_GUARD_REQUIRED_PATHS = ['web/modules/custom', 'web/themes/custom', 'scripts'];
const PHPSTAN_GUARD_ALLOWED_EXCLUDES = ['vendor/', 'web/core/', 'web/libraries/'];

$command = $argv[1] ?? '';
$options = phpstan_guard_options(array_slice($argv, 2));
$violations = [];
$notes = [];

try {
  switch ($command) {
    case 'check':
      phpstan_guard_check($options, $violations, $notes);
      break;

    case 'diff':
      phpstan_guard_diff($options, $violations, $notes);
      break;

    default:
      fwrite(STDERR, "Uso: phpstan-debt-guard.php check|diff [--opção=valor]\n");
      exit(2);
  }
}
catch (RuntimeException $exception) {
  fwrite(STDERR, 'ERRO DE INFRAESTRUTURA: ' . $exception->getMessage() . "\n");
  exit(2);
}

foreach ($notes as $note) {
  fwrite(STDOUT, 'INFO: ' . $note . "\n");
}
if ($violations !== []) {
  foreach ($violations as $violation) {
    fwrite(STDERR, 'FALHA: ' . $violation . "\n");
  }
  fwrite(STDERR, sprintf("PHPStan debt guard: %d violação(ões).\n", count($violations)));
  exit(1);
}
fwrite(STDOUT, "PHPStan debt guard: PASS\n");
exit(0);

/**
 * Implements the `check` command.
 *
 * @param array<string, string> $options
 *   CLI options.
 * @param array<int, string> $violations
 *   Violations, appended by reference.
 * @param array<int, string> $notes
 *   Informational notes, appended by reference.
 */
function phpstan_guard_check(array $options, array &$violations, array &$notes): void {
  $baselinePath = phpstan_guard_path($options, 'baseline', PHPSTAN_GUARD_CANONICAL_BASELINE);
  $configPath = phpstan_guard_path($options, 'config', 'phpstan.neon');
  $entries = phpstan_guard_load_baseline($baselinePath, $violations);

  phpstan_guard_validate_config($configPath, $violations);

  $reference = phpstan_guard_reference($options, $notes);
  if ($reference !== NULL) {
    $referenceLevel = phpstan_guard_reference_level($reference);
    if ($referenceLevel < PHPSTAN_GUARD_REQUIRED_LEVEL) {
      $notes[] = sprintf(
        'referência no nível %d: comparação de crescimento suspensa nesta transição; o nível 10 passa a valer a partir do próximo commit.',
        $referenceLevel,
      );
    }
    else {
      $comparison = PhpstanDebt::compare($reference['entries'], $entries);
      foreach ($comparison['added'] as $key) {
        $violations[] = 'nova dívida no baseline: ' . phpstan_guard_describe_key($key);
      }
      foreach ($comparison['increased'] as $increase) {
        $violations[] = sprintf(
          'contagem aumentada em %s: %d -> %d',
          phpstan_guard_describe_key($increase['key']),
          $increase['reference'],
          $increase['current'],
        );
      }
      foreach ($comparison['modified'] as $key) {
        $violations[] = 'identidade de erro alterada (revisão obrigatória): ' . phpstan_guard_describe_key($key);
      }
      $removed = count($comparison['removed']);
      $decreased = count($comparison['decreased']);
      if ($removed > 0 || $decreased > 0) {
        $notes[] = sprintf('redução legítima: %d entrada(s) removida(s), %d contagem(ns) reduzida(s).', $removed, $decreased);
      }
    }
  }
  else {
    $notes[] = 'sem baseline de referência: verificação de crescimento não aplicada.';
  }

  // The JSON must come from the canonical baseline-applied run. PHPStan then
  // reports only unsuppressed errors plus ignore.unmatched/ignore.count, so
  // the gate never depends on how the baseline exporter attributes
  // trait/context errors to a file.
  $analysisPath = $options['analysis'] ?? NULL;
  if ($analysisPath !== NULL && $analysisPath !== '') {
    foreach (phpstan_guard_analysis($analysisPath) as $message) {
      $identifier = $message['identifier'];
      if (str_starts_with($identifier, 'ignore.')) {
        $violations[] = sprintf(
          'baseline inconsistente (%s) em %s: %s',
          $identifier,
          $message['path'],
          PhpstanDebt::normalizeMessage($message['message']),
        );
        continue;
      }
      $violations[] = sprintf(
        'erro não suprimido em %s [%s]: %s',
        $message['path'],
        $identifier !== '' ? $identifier : 'sem.identificador',
        PhpstanDebt::normalizeMessage($message['message']),
      );
    }
  }
}

/**
 * Implements the `diff` command: debt in touched files and modules.
 *
 * @param array<string, string> $options
 *   CLI options.
 * @param array<int, string> $violations
 *   Violations, appended by reference.
 * @param array<int, string> $notes
 *   Informational notes, appended by reference.
 */
function phpstan_guard_diff(array $options, array &$violations, array &$notes): void {
  $baselinePath = phpstan_guard_path($options, 'baseline', PHPSTAN_GUARD_CANONICAL_BASELINE);
  $entries = phpstan_guard_load_baseline($baselinePath, $violations);
  $changed = phpstan_guard_changed_files($options);
  if ($changed === []) {
    $notes[] = 'nenhum arquivo PHP alterado no diff: gate de dívida tocada N/A.';
    return;
  }

  $touchedModules = [];
  foreach ($changed as $path) {
    $touchedModules[PhpstanDebt::module($path)] = TRUE;
    $fileDebt = 0;
    foreach ($entries as $entry) {
      if ($entry['path'] === $path) {
        $fileDebt += $entry['count'];
      }
    }
    if ($fileDebt > 0) {
      $violations[] = sprintf(
        '%s tem %d ocorrência(s) de dívida congelada e precisa ser corrigido nesta alteração.',
        $path,
        $fileDebt,
      );
    }
  }

  // New inline suppressions are forbidden: only debt frozen in the baseline
  // may remain suppressed. A new error must be fixed, never ignored.
  foreach (phpstan_guard_new_ignores(phpstan_guard_diff_text($options)) as $ignore) {
    $violations[] = sprintf(
      'novo @phpstan-ignore proibido em %s:%d; corrija o erro em vez de suprimi-lo.',
      $ignore['path'],
      $ignore['line'],
    );
  }

  $reference = phpstan_guard_reference($options, $notes);
  if ($reference === NULL) {
    $notes[] = 'sem baseline de referência: meta de redução por módulo não aplicada.';
    return;
  }
  if (phpstan_guard_reference_level($reference) < PHPSTAN_GUARD_REQUIRED_LEVEL) {
    $notes[] = 'referência em nível inferior: meta de redução por módulo suspensa na transição.';
    return;
  }

  $minimum = max(1, (int) ($options['module-min-reduction'] ?? '1'));
  $referenceByModule = PhpstanDebt::countsByModule($reference['entries']);
  $currentByModule = PhpstanDebt::countsByModule($entries);
  foreach (array_keys($touchedModules) as $module) {
    $before = $referenceByModule[$module] ?? 0;
    $after = $currentByModule[$module] ?? 0;
    if ($after > $before) {
      $violations[] = sprintf('dívida do módulo %s cresceu: %d -> %d.', $module, $before, $after);
      continue;
    }
    if ($before > 0 && ($before - $after) < $minimum) {
      $violations[] = sprintf(
        'módulo %s foi alterado e precisa reduzir ao menos %d ocorrência(s) de dívida (atual: %d -> %d).',
        $module,
        $minimum,
        $before,
        $after,
      );
    }
  }
  $notes[] = 'módulos tocados: ' . implode(', ', array_keys($touchedModules)) . '.';
}

/**
 * Loads and validates a baseline file.
 *
 * @param array<int, string> $violations
 *   Violations, appended by reference.
 *
 * @return list<array{path: string, identifier: string, message: string, count: int, valid: bool, error: string}>
 *   Parsed entries.
 */
function phpstan_guard_load_baseline(string $path, array &$violations): array {
  if (!is_file($path)) {
    throw new RuntimeException("baseline não encontrado: {$path}");
  }
  $contents = file_get_contents($path);
  if ($contents === FALSE) {
    throw new RuntimeException("não foi possível ler o baseline: {$path}");
  }
  $entries = PhpstanDebt::parseBaseline($contents);
  if ($entries === []) {
    throw new RuntimeException("baseline vazio ou ilegível: {$path}");
  }
  $seen = [];
  foreach ($entries as $entry) {
    if (!$entry['valid']) {
      $violations[] = sprintf('baseline inválido em %s: %s.', $entry['path'] !== '' ? $entry['path'] : '(sem path)', $entry['error']);
      continue;
    }
    $key = PhpstanDebt::key($entry);
    if (isset($seen[$key])) {
      $violations[] = 'entrada duplicada no baseline: ' . phpstan_guard_describe_key($key);
      continue;
    }
    $seen[$key] = TRUE;
  }
  return $entries;
}

/**
 * Validates phpstan.neon strictly enough to prevent weakening the gate.
 *
 * @param array<int, string> $violations
 *   Violations, appended by reference.
 */
function phpstan_guard_validate_config(string $path, array &$violations): void {
  if (!is_file($path)) {
    throw new RuntimeException("configuração PHPStan não encontrada: {$path}");
  }
  $contents = (string) file_get_contents($path);
  if (preg_match('/^\s*level:\s*(\d+)\s*$/m', $contents, $match) !== 1) {
    $violations[] = 'phpstan.neon não declara level explicitamente.';
  }
  elseif ((int) $match[1] < PHPSTAN_GUARD_REQUIRED_LEVEL) {
    $violations[] = sprintf('phpstan.neon está no nível %s; o mínimo obrigatório é %d.', $match[1], PHPSTAN_GUARD_REQUIRED_LEVEL);
  }
  if (preg_match('/^\s*reportUnmatchedIgnoredErrors:\s*(true|false)\s*$/m', $contents, $match) !== 1) {
    $violations[] = 'phpstan.neon não declara reportUnmatchedIgnoredErrors.';
  }
  elseif ($match[1] !== 'true') {
    $violations[] = 'reportUnmatchedIgnoredErrors precisa permanecer true.';
  }
  $baselineIncludes = preg_match_all('/^\s*-\s*phpstan-baseline\.neon\s*$/m', $contents);
  if ($baselineIncludes !== 1) {
    $violations[] = sprintf('phpstan.neon deve incluir %s exatamente uma vez (encontrado: %d).', PHPSTAN_GUARD_CANONICAL_BASELINE, (int) $baselineIncludes);
  }
  if (preg_match('/^\s*ignoreErrors:/m', $contents) === 1) {
    $violations[] = 'phpstan.neon não pode conter ignoreErrors; toda exceção vive no baseline congelado.';
  }
  foreach (PHPSTAN_GUARD_REQUIRED_PATHS as $required) {
    if (preg_match('/^\s*-\s*' . preg_quote($required, '/') . '\s*$/m', $contents) !== 1) {
      $violations[] = "escopo de análise reduzido: path obrigatório ausente ({$required}).";
    }
  }
  if (preg_match('/^\s*excludePaths:\s*$(.*?)(?=^\S|\z)/ms', $contents, $match) === 1) {
    preg_match_all('/^\s*-\s*([^\s#]+)\s*$/m', $match[1], $excludes);
    foreach ($excludes[1] as $exclude) {
      if (!in_array($exclude, PHPSTAN_GUARD_ALLOWED_EXCLUDES, TRUE)) {
        $violations[] = "exclusão não autorizada em phpstan.neon: {$exclude}.";
      }
    }
  }
}

/**
 * Resolves the reference baseline from a file or a git revision.
 *
 * @param array<string, string> $options
 *   CLI options.
 * @param array<int, string> $notes
 *   Informational notes, appended by reference.
 *
 * @return array{entries: list<array{path: string, identifier: string, message: string, count: int, valid: bool, error: string}>, level: int|null}|null
 *   Reference data, or NULL when no reference is available.
 */
function phpstan_guard_reference(array $options, array &$notes): ?array {
  if (isset($options['reference-baseline']) && $options['reference-baseline'] !== '') {
    $path = $options['reference-baseline'];
    if (!is_file($path)) {
      throw new RuntimeException("baseline de referência não encontrado: {$path}");
    }
    $entries = PhpstanDebt::parseBaseline((string) file_get_contents($path));
    $level = isset($options['reference-level']) ? (int) $options['reference-level'] : PHPSTAN_GUARD_REQUIRED_LEVEL;
    return ['entries' => $entries, 'level' => $level];
  }
  $base = $options['git-base'] ?? NULL;
  if ($base === NULL || $base === '') {
    $default = phpstan_guard_run(['git', 'rev-parse', '--verify', '--quiet', 'origin/dev']);
    if ($default['code'] !== 0 || trim($default['output']) === '') {
      return NULL;
    }
    $base = 'origin/dev';
  }
  $baseline = phpstan_guard_run(['git', 'show', $base . ':' . PHPSTAN_GUARD_CANONICAL_BASELINE]);
  if ($baseline['code'] !== 0) {
    $notes[] = "baseline ausente no commit de referência {$base}: comparação de crescimento não aplicada.";
    return NULL;
  }
  $entries = PhpstanDebt::parseBaseline($baseline['output']);
  $config = phpstan_guard_run(['git', 'show', $base . ':' . ($options['config'] ?? 'phpstan.neon')]);
  $level = 0;
  if ($config['code'] === 0 && preg_match('/^\s*level:\s*(\d+)\s*$/m', $config['output'], $match) === 1) {
    $level = (int) $match[1];
  }
  return ['entries' => $entries, 'level' => $level];
}

/**
 * Returns the reference level, reading it from git when available.
 *
 * @param array{entries: array<int, array<string, mixed>>, level: int|null} $reference
 *   Reference data.
 */
function phpstan_guard_reference_level(array $reference): int {
  return (int) ($reference['level'] ?? PHPSTAN_GUARD_REQUIRED_LEVEL);
}

/**
 * Returns the unified diff used to detect newly added inline suppressions.
 *
 * @param array<string, string> $options
 *   CLI options.
 */
function phpstan_guard_diff_text(array $options): string {
  $file = $options['diff-text'] ?? NULL;
  if ($file !== NULL && $file !== '') {
    if (!is_file($file)) {
      throw new RuntimeException("diff de referência não encontrado: {$file}");
    }
    return (string) file_get_contents($file);
  }
  $base = $options['git-base'] ?? NULL;
  if ($base === NULL || $base === '') {
    return '';
  }
  $command = ['git', 'diff', '--unified=0', '--diff-filter=ACMR', $base, 'HEAD'];
  $diff = phpstan_guard_run($command);
  if ($diff['code'] !== 0) {
    throw new RuntimeException("git diff falhou para detecção de ignores: " . trim($diff['output']));
  }
  return $diff['output'];
}

/**
 * Finds inline PHPStan suppressions added by the diff.
 *
 * @return list<array{path: string, line: int}>
 *   Added suppressions with their file and new line number.
 */
function phpstan_guard_new_ignores(string $diffText): array {
  $ignores = [];
  $path = '';
  $line = 0;
  foreach (preg_split('/\R/', $diffText) ?: [] as $row) {
    if (str_starts_with($row, '+++ b/')) {
      $path = substr($row, 6);
      continue;
    }
    if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $row, $match) === 1) {
      $line = (int) $match[1];
      continue;
    }
    if ($path === '' || !str_ends_with($path, '.php') || !str_starts_with($row, '+') || str_starts_with($row, '+++')) {
      $line++;
      continue;
    }
    if (preg_match('/@phpstan-ignore(-next-line|-line)?\b/', $row) === 1) {
      $ignores[] = ['path' => $path, 'line' => $line];
    }
    $line++;
  }
  return $ignores;
}

/**
 * Returns changed PHP files from an explicit list or git diff.
 *
 * @return list<string>
 *   Repository-relative paths.
 */
function phpstan_guard_changed_files(array $options): array {
  $listFile = $options['changed-files'] ?? NULL;
  if ($listFile !== NULL && $listFile !== '') {
    if (!is_file($listFile)) {
      throw new RuntimeException("lista de arquivos alterados não encontrada: {$listFile}");
    }
    $paths = preg_split('/\R/', (string) file_get_contents($listFile)) ?: [];
  }
  else {
    $base = $options['git-base'] ?? NULL;
    if ($base === NULL || $base === '') {
      throw new RuntimeException('informe --git-base ou --changed-files para o gate de diff.');
    }
    $command = ['git', 'diff', '--name-only', '--diff-filter=ACMR', $base, 'HEAD'];
    $diff = phpstan_guard_run($command);
    if ($diff['code'] !== 0) {
      throw new RuntimeException("git diff falhou para {$base}: " . trim($diff['output']));
    }
    $paths = preg_split('/\R/', $diff['output']) ?: [];
    if (($options['include-worktree'] ?? '') === 'true') {
      $worktree = phpstan_guard_run(['git', 'diff', '--name-only', '--diff-filter=ACMR', $base]);
      if ($worktree['code'] === 0) {
        $paths = array_merge($paths, preg_split('/\R/', $worktree['output']) ?: []);
      }
    }
  }
  $result = [];
  foreach ($paths as $path) {
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || !str_ends_with($path, '.php')) {
      continue;
    }
    if (str_starts_with($path, './')) {
      $path = substr($path, 2);
    }
    $result[$path] = TRUE;
  }
  $keys = array_keys($result);
  sort($keys);
  return $keys;
}

/**
 * Reads a PHPStan JSON report produced with the canonical baseline applied.
 *
 * @return list<array{key: string, path: string, identifier: string, message: string}>
 *   One item per reported error.
 */
function phpstan_guard_analysis(string $path): array {
  if (!is_file($path)) {
    throw new RuntimeException("relatório PHPStan não encontrado: {$path}");
  }
  $contents = file_get_contents($path);
  if ($contents === FALSE) {
    throw new RuntimeException("não foi possível ler o relatório PHPStan: {$path}");
  }
  $data = json_decode($contents, TRUE);
  if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) {
    throw new RuntimeException("JSON do PHPStan inválido ou interrompido: {$path}");
  }
  $root = rtrim((string) getcwd(), '/') . '/';
  $messages = [];
  foreach ($data['files'] as $file => $fileData) {
    if (!is_array($fileData) || !isset($fileData['messages']) || !is_array($fileData['messages'])) {
      throw new RuntimeException("estrutura de arquivo inválida no JSON do PHPStan: {$file}");
    }
    $filePath = str_replace('\\', '/', (string) $file);
    if (str_starts_with($filePath, $root)) {
      $filePath = substr($filePath, strlen($root));
    }
    foreach ($fileData['messages'] as $message) {
      if (!is_array($message) || !isset($message['message']) || !is_string($message['message'])) {
        throw new RuntimeException("mensagem inválida no JSON do PHPStan: {$file}");
      }
      $identifier = isset($message['identifier']) && is_string($message['identifier']) ? $message['identifier'] : '';
      $entry = [
        'path' => $filePath,
        'identifier' => $identifier,
        'message' => $message['message'],
        'count' => 1,
      ];
      $messages[] = [
        'key' => PhpstanDebt::key($entry),
        'path' => $filePath,
        'identifier' => $identifier,
        'message' => $message['message'],
      ];
    }
  }
  return $messages;
}

/**
 * Runs a command without a shell and returns its output and exit code.
 *
 * @param list<string> $command
 *   Command and arguments.
 *
 * @return array{code: int, output: string}
 *   Exit code and captured output.
 */
function phpstan_guard_run(array $command): array {
  $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $process = proc_open($command, $descriptors, $pipes);
  if (!is_resource($process)) {
    throw new RuntimeException('não foi possível executar: ' . implode(' ', $command));
  }
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $code = proc_close($process);
  return ['code' => $code, 'output' => (string) $stdout . (string) $stderr];
}

/**
 * Reads a CLI option in --name=value form.
 *
 * @param array<int, string> $args
 *   Raw arguments.
 *
 * @return array<string, string>
 *   Options by name.
 */
function phpstan_guard_options(array $args): array {
  $options = [];
  foreach ($args as $arg) {
    if (preg_match('/^--([a-z-]+)=(.*)$/s', $arg, $match) === 1) {
      $options[$match[1]] = $match[2];
    }
  }
  return $options;
}

/**
 * Returns a required path option with a default.
 *
 * @param array<string, string> $options
 *   CLI options.
 */
function phpstan_guard_path(array $options, string $name, string $default): string {
  return ($options[$name] ?? '') !== '' ? $options[$name] : $default;
}

/**
 * Formats a baseline key for a human-readable message.
 */
function phpstan_guard_describe_key(string $key): string {
  $parts = explode("\0", $key, 3);
  $message = $parts[2] ?? '';
  if (strlen($message) > 120) {
    $message = substr($message, 0, 117) . '...';
  }
  return sprintf('%s [%s] %s', $parts[0], $parts[1] ?? '', $message);
}
