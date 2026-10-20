<?php

/**
 * @file
 * Validates the fixed version and next-phase table in the root README.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$readmePath = $root . '/README.md';
$readme = is_file($readmePath) ? file_get_contents($readmePath) : FALSE;
$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
  if (!$condition) {
    $errors[] = $message;
  }
};

$begin = '<!-- PROJECT-STATUS-INDEX:BEGIN -->';
$end = '<!-- PROJECT-STATUS-INDEX:END -->';
$assert(is_string($readme), 'README.md must exist and be readable.');
$assert(is_string($readme) && substr_count($readme, $begin) === 1 && substr_count($readme, $end) === 1, 'The protected PROJECT-STATUS-INDEX markers must appear exactly once.');
$agents = (string) @file_get_contents($root . '/AGENTS.md');
$portalGate = (string) @file_get_contents($root . '/scripts/validate-aculta-portal-drupal11.php');
$assert(str_contains($agents, 'PROJECT-STATUS-INDEX-GUARD: 2026-10-20'), 'AGENTS.md must retain the permanent status-index guardrail marker.');
$assert(str_contains($agents, 'php scripts/validate-project-status-index.php'), 'AGENTS.md must require the status-index gate for component changes.');
$assert(str_contains($portalGate, 'validate-project-status-index.php'), 'The Portal architecture gate must invoke the project status-index gate.');

$rows = [];
$rowCount = 0;
if (is_string($readme) && substr_count($readme, $begin) === 1 && substr_count($readme, $end) === 1) {
  $table = substr($readme, strpos($readme, $begin) + strlen($begin), strpos($readme, $end) - strpos($readme, $begin) - strlen($begin));
  foreach (preg_split('/\R/', $table) ?: [] as $line) {
    if (!str_starts_with(trim($line), '|')) {
      continue;
    }
    $cells = array_map('trim', explode('|', trim($line, " \t|")));
    if (count($cells) < 4 || $cells[0] === 'Componente' || preg_match('/^-+$/', $cells[0])) {
      continue;
    }
    if (!preg_match('/`([a-z0-9_]+)`/', $cells[0], $componentMatch)) {
      $errors[] = 'A component table row must use a machine name in backticks: ' . $line;
      continue;
    }
    $machine = $componentMatch[1];
    $rowCount++;
    if (isset($rows[$machine])) {
      $errors[] = "Duplicate status table row for $machine.";
    }
    $rows[$machine] = [
      'component' => $cells[0],
      'version' => $cells[1],
      'phase' => $cells[2],
      'status' => $cells[3],
    ];
  }
}

$manifests = [];
$portalRoot = $root . '/web/modules/custom/aculta_portal';
$portalManifest = $portalRoot . '/aculta_portal.info.yml';
if (is_file($portalManifest)) {
  $manifests['aculta_portal'] = $portalManifest;
}
$moduleRoot = $portalRoot . '/modules';
if (is_dir($moduleRoot)) {
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.info.yml') || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
      continue;
    }
    $machine = substr($file->getFilename(), 0, -strlen('.info.yml'));
    $manifests[$machine] = $file->getPathname();
  }
}
$themeManifest = $root . '/web/themes/custom/aculta420/aculta420.info.yml';
if (is_file($themeManifest)) {
  $manifests['aculta420'] = $themeManifest;
}

$extractVersion = static function (string $manifest): ?string {
  $contents = file_get_contents($manifest);
  if (!is_string($contents) || !preg_match('/^version:\s*[\'\"]?([^\'\"\r\n]+)/m', $contents, $match)) {
    return NULL;
  }
  return trim($match[1]);
};
$extractLinks = static function (string $markdown): array {
  preg_match_all('/\]\(([^)#]+)(?:#[^)]*)?\)/', $markdown, $matches);
  return $matches[1] ?? [];
};

$assert(count($manifests) > 0, 'No custom Portal modules or ACULTA420 theme manifests were found.');
$assert($rowCount === count($rows), 'The status table contains duplicate component rows.');
$missing = array_diff(array_keys($manifests), array_keys($rows));
$extra = array_diff(array_keys($rows), array_keys($manifests));
$assert($missing === [], 'README status table is missing components: ' . implode(', ', $missing));
$assert($extra === [], 'README status table contains unknown/duplicate component IDs: ' . implode(', ', $extra));

foreach ($manifests as $machine => $manifest) {
  if (!isset($rows[$machine])) {
    continue;
  }
  $row = $rows[$machine];
  $version = $extractVersion($manifest);
  if ($version === NULL) {
    $assert(str_contains(strtolower($row['version']), 'versão não declarada'), "$machine has no manifest version; state that explicitly in the table.");
  }
  else {
    $assert(str_contains($row['version'], $version), "$machine table version does not match manifest version $version.");
  }
  if ($machine === 'aculta_deployer' && is_file(dirname($manifest) . '/VERSION')) {
    $cliVersion = trim((string) file_get_contents(dirname($manifest) . '/VERSION'));
    $assert(str_contains($row['version'], $cliVersion), "aculta_deployer table must also include CLI version $cliVersion.");
  }
  $expectedReadme = dirname($manifest) . '/README.md';
  $componentLinks = $extractLinks($row['component']);
  $assert(is_file($expectedReadme), "$machine must have its entry README.");
  $assert(in_array(substr($expectedReadme, strlen($root) + 1), $componentLinks, TRUE), "$machine table row must link to its own README.");
  foreach (array_merge($componentLinks, $extractLinks($row['phase'])) as $link) {
    if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
      continue;
    }
    $assert(is_file($root . '/' . $link), "$machine table has a missing local link: $link");
  }
  $assert(trim(strip_tags($row['phase'])) !== '', "$machine must state its next phase.");
  $assert(in_array($row['status'], ['Dev', 'preRC', 'RC'], TRUE), "$machine state must be Dev, preRC, or RC.");
  if ($row['status'] === 'preRC') {
    $assert(stripos($row['version'], 'prerc') !== FALSE, "$machine is preRC but its declared version does not carry the preRC label.");
  }
  if ($row['status'] === 'RC') {
    $assert(stripos($row['version'], '-rc') !== FALSE, "$machine is RC but its declared version is not an RC version.");
  }
}

// Starting with the commit that adds this guardrail, every component change
// must update the root table in the same commit. Check history and worktree.
$git = static function (array $arguments) use ($root): array {
  $command = array_merge(['git', '-C', $root], $arguments);
  $descriptors = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
  ];
  $process = proc_open($command, $descriptors, $pipes);
  if (!is_resource($process)) {
    return [1, ''];
  }
  $stdout = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  return [proc_close($process), trim((string) $stdout)];
};
$extensionPath = static fn(string $path): bool => str_starts_with($path, 'web/modules/custom/aculta_portal/')
  || str_starts_with($path, 'web/themes/custom/aculta420/');
[$markerCode, $markerCommit] = $git([
  'log', '-1', '--format=%H', '-SPROJECT-STATUS-INDEX-GUARD: 2026-10-20', '--', 'AGENTS.md',
]);
if ($markerCode === 0 && $markerCommit !== '') {
  [$historyCode, $history] = $git(['rev-list', '--reverse', $markerCommit . '..HEAD']);
  $assert($historyCode === 0, 'Unable to audit commits since the status-index guardrail was introduced.');
  foreach (preg_split('/\R/', $history) ?: [] as $commit) {
    if ($commit === '') {
      continue;
    }
    [, $files] = $git(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit]);
    $paths = preg_split('/\R/', $files) ?: [];
    $componentTouched = array_filter($paths, $extensionPath);
    if ($componentTouched !== []) {
      $message = sprintf('Commit %s changes a module/theme without updating root README.md.', substr($commit, 0, 12));
      $assert(in_array('README.md', $paths, TRUE), $message);
    }
  }
}
[$worktreeCode, $worktree] = $git(['diff', '--name-only', 'HEAD', '--']);
$assert($worktreeCode === 0, 'Unable to inspect staged/unstaged component changes.');
$worktreePaths = preg_split('/\R/', $worktree) ?: [];
$uncommittedComponentChanges = array_filter($worktreePaths, $extensionPath);
if ($uncommittedComponentChanges !== []) {
  $assert(in_array('README.md', $worktreePaths, TRUE), 'Uncommitted module/theme changes require an updated root README.md in the same commit.');
}

if ($errors !== []) {
  fwrite(STDERR, "PROJECT STATUS INDEX: FAIL\n- " . implode("\n- ", $errors) . "\n");
  exit(1);
}

printf("PROJECT STATUS INDEX: PASS (%d components; versions, phases, links and guardrail verified)\n", count($manifests));
