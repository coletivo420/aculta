<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Runs the standalone deployment commands.
 *
 * The CLI does not depend on Drupal, Drush, or Composer vendor packages.
 */
final class Cli {

  public const VERSION = '0.1.28-rc.1';

  /**
   * Absolute path to this tool's module directory.
   *
   * @var string
   */
  private readonly string $toolRoot;

  /**
   * Absolute path to the project repository root.
   *
   * @var string
   */
  private readonly string $repoRoot;

  /**
   * Creates the CLI handler for the installed module directory.
   *
   * @param string $toolRoot
   *   Absolute path to the Deployer module.
   */
  public function __construct(string $toolRoot) {
    $this->toolRoot = $toolRoot;
    // The module is six directory levels below the repository root.
    $this->repoRoot = dirname($toolRoot, 6);
  }

  /**
   * Dispatches a command from the command-line arguments.
   *
   * @param string[] $argv
   *   Arguments passed to the CLI process.
   *
   * @return int
   *   Process exit code.
   */
  public function run(array $argv): int {
    $command = $argv[1] ?? 'help';
    $options = $this->options(array_slice($argv, 2));
    return match ($command) {
      'version', '--version' => $this->say('aculta-deployer ' . self::VERSION),
      'check' => $this->check(!empty($options['strict'])),
      'boundaries' => $this->boundaries(),
      'list' => $this->list(),
      'register' => $this->register($options),
      'build' => $this->build($options),
      'hostinger-layout' => $this->hostingerLayout($options),
      'transition' => $this->transition($options),
      'domains' => $this->domains(),
      'cookies' => $this->cookies(),
      'webhooks' => $this->webhooks(),
      'provision' => $this->provision($options),
      'verify' => $this->verify($options),
      'robots' => $this->robots($options),
      'sitemap' => $this->sitemap($options),
      'report' => $this->report($options),
      'environment' => $this->environment((string) ($argv[2] ?? ''), $options),
      default => $this->help(),
    };
  }

  /**
   * Validates configuration, boundaries, and the deploy registry.
   *
   * @param bool $strict
   *   Whether blocking registry entries should fail the command.
   *
   * @return int
   *   Process exit code.
   */
  private function check(bool $strict): int {
    $code = 0;
    $registry = $this->registry();
    $errors = $registry->validate();
    foreach ($errors as $e) {
      $this->err("registro: $e");
      $code = 1;
    }
    $boundary = $this->boundaryViolations();
    foreach ($boundary as $v) {
      $this->err("fronteira: $v");
      $code = 1;
    }
    $transform = $this->transform();
    foreach ($transform->validate() as $e) {
      $this->err("configuração: $e");
      $code = 1;
    }
    // Environment-specific files require profile rules for every environment.
    foreach ($transform->validateProfiles() as $e) {
      $this->err("perfil: $e");
      $code = 1;
    }
    foreach ($this->unboundEnvironmentValues() as $rel => $email) {
      $this->err("perfil: $rel tem endereço fixo ($email) e não está em environment_bound; declare o arquivo e crie regras nos perfis");
      $code = 1;
    }
    foreach ($this->profileRuleFailures($transform) as $e) {
      $this->err("perfil: $e");
      $code = 1;
    }
    $stats = $this->scan($transform);
    $this->say(sprintf('escopo: %d arquivos; %d com host de teste; %d removidos no build de produção',
      $stats['scope'], $stats['with_test_host'], $stats['dropped']));
    foreach ($stats['outside_scope'] as $rel) {
      $this->say("  fora do escopo (documentação ou teste; não transformado): $rel");
    }
    $open = $registry->open();
    $blocking = $registry->openBlocking();
    $this->say(sprintf('registro: %d entradas abertas, %d bloqueantes', count($open), count($blocking)));
    foreach ($blocking as $e) {
      $this->say("  bloqueante {$e['id']} ({$e['kind']}): {$e['page']}");
    }
    if ($code === 0 && $strict && $blocking !== []) {
      $this->err('--strict: existem entradas bloqueantes abertas');
      return 2;
    }
    $this->say($code === 0 ? 'check: PASS' : 'check: FAIL');
    // Session cookie settings must match the session_cookie environment policy.
    if ($this->cookies() !== 0) {
      $code = 1;
    }
    return $code;
  }

  /**
   * Checks that consumers and the tool respect their dependency boundaries.
   *
   * @return int
   *   Process exit code.
   */
  private function boundaries(): int {
    $violations = $this->boundaryViolations();
    foreach ($violations as $v) {
      $this->err("fronteira: $v");
    }
    if ($violations !== []) {
      $this->say('boundaries: FAIL');
      return 1;
    }
    $this->say('boundaries: PASS (Portal e tema não referenciam a ferramenta; a ferramenta não depende deles)');
    return 0;
  }

  /**
   * Lists correction registry entries.
   *
   * @return int
   *   Process exit code.
   */
  private function list(): int {
    foreach ($this->registry()->entries() as $e) {
      $this->say(sprintf('%s [%s] %s%s — %s', $e['id'], $e['status'], $e['kind'], ($e['blocking'] ?? FALSE) ? ' (bloqueante)' : '', $e['page']));
    }
    return 0;
  }

  /**
   * Checks indexing policy across the configured hosts using read-only GETs.
   *
   * Production must be indexable; the test environment must use noindex.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function robots(array $o): int {
    $env = $this->activeEnvironment($o);
    $policy = $this->json($this->toolRoot . '/config/deploy.json')['robots_policy'][$env] ?? NULL;
    if ($policy === NULL) {
      $this->err("robots: ambiente desconhecido: $env");
      return 1;
    }
    $code = 0;
    $checks = [];
    foreach ($policy['hosts'] as $url) {
      $res = Verify::fetchWithHeaders((string) $url);
      if ($res === NULL) {
        $this->say("FAIL $url: sem resposta");
        $checks[] = ['kind' => 'host', 'url' => (string) $url, 'ok' => FALSE, 'observed' => 'sem resposta'];
        $code = 1;
        continue;
      }
      $value = Verify::robotsHeader($res['headers']);
      $noindex = Verify::isNoindex($value);
      $ok = $env === 'production' ? !$noindex : $noindex;
      $this->say(sprintf('%s %s: X-Robots-Tag=%s', $ok ? 'PASS' : 'FAIL', $url, $value ?? '(ausente)'));
      $checks[] = ['kind' => 'host', 'url' => (string) $url, 'ok' => $ok, 'observed' => $value ?? 'ausente'];
      $code = $ok ? $code : 1;
    }
    if ($env === 'production') {
      $indexUrl = (string) ($this->json($this->toolRoot . '/config/deploy.json')['sitemap']['production']['index_url'] ?? '');
      foreach ($policy['hosts'] as $url) {
        $res = Verify::fetchWithHeaders(rtrim((string) $url, '/') . '/robots.txt');
        if ($res === NULL || Verify::statusCode($res['headers']) !== 200) {
          $this->say("FAIL $url robots.txt: sem resposta 200");
          $checks[] = ['kind' => 'robots_txt', 'url' => (string) $url, 'ok' => FALSE, 'observed' => 'sem resposta 200'];
          $code = 1;
          continue;
        }
        $advertised = in_array($indexUrl, Verify::sitemapDirectives($res['body']), TRUE);
        $blocked = Verify::disallowsRoot($res['body']);
        $ok = $advertised && !$blocked;
        $this->say(sprintf('%s %s robots.txt: Sitemap=%s, Disallow: / %s', $ok ? 'PASS' : 'FAIL', $url, $advertised ? 'índice' : 'ausente', $blocked ? 'presente' : 'ausente'));
        $checks[] = [
          'kind' => 'robots_txt',
          'url' => (string) $url,
          'ok' => $ok,
          'observed' => ($advertised ? 'Sitemap índice' : 'Sitemap ausente')
          . ', Disallow: / ' . ($blocked ? 'presente' : 'ausente'),
        ];
        $code = $ok ? $code : 1;
      }
    }
    foreach ($policy['private_probes'] ?? [] as $url) {
      $res = Verify::fetchWithHeaders((string) $url);
      if ($res === NULL) {
        $this->say("FAIL $url: sem resposta (caminho privado)");
        $checks[] = ['kind' => 'private', 'url' => (string) $url, 'ok' => FALSE, 'observed' => 'sem resposta'];
        $code = 1;
        continue;
      }
      $value = Verify::robotsHeader($res['headers']);
      $status = Verify::statusCode($res['headers']);
      $meta = Verify::metaNoindex($res['body']);
      // Private paths must be noindex in headers or HTML, or refuse access.
      $ok = Verify::isNoindex($value) || $meta || Verify::isRefusedStatus($status);
      $this->say(sprintf('%s %s (privado): status=%s X-Robots-Tag=%s meta=%s',
        $ok ? 'PASS' : 'FAIL', $url, $status ?? '?', $value ?? '(ausente)', $meta ? 'noindex' : 'ausente'));
      $checks[] = [
        'kind' => 'private',
        'url' => (string) $url,
        'ok' => $ok,
        'observed' => 'status ' . ($status ?? '?')
        . ', X-Robots-Tag ' . ($value ?? 'ausente')
        . ', meta ' . ($meta ? 'noindex' : 'ausente'),
      ];
      $code = $ok ? $code : 1;
    }
    $this->writeIndexing($env, $checks, $code === 0);
    $this->say("robots ($env): " . ($code === 0 ? 'PASS' : 'FAIL'));
    return $code;
  }

  /**
   * Checks sitemap discovery and content hosts with read-only GET requests.
   *
   * The index and children must use the environment base. Child URLs must be in
   * the production host policy and each content host must respond.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function sitemap(array $o): int {
    $env = $this->activeEnvironment($o);
    $cfg = $this->json($this->toolRoot . '/config/deploy.json');
    $policy = $cfg['sitemap'][$env] ?? NULL;
    if ($policy === NULL) {
      $this->err("sitemap: ambiente desconhecido: $env");
      return 1;
    }
    // Test mode compares each production host with its test equivalent.
    $allowed = [];
    foreach ($cfg['robots_policy']['production']['hosts'] ?? [] as $url) {
      $host = (string) Verify::hostOf((string) $url);
      $allowed[] = $env === 'test' ? Verify::testEquivalent($host) : $host;
    }
    $base = Verify::hostOf((string) $policy['index_base']);
    $code = 0;
    $index = Verify::fetchWithHeaders((string) $policy['index_url']);
    $indexLocs = $index === NULL ? NULL : Verify::xmlLocs($index['body']);
    if ($indexLocs === NULL || $indexLocs === []) {
      $this->say("FAIL {$policy['index_url']}: índice ausente, sem resposta ou sem <loc>");
      $this->say("sitemap ($env): FAIL");
      return 1;
    }
    $this->say("PASS {$policy['index_url']}: índice com " . count($indexLocs) . ' sitemap(s)');
    $contentHosts = [];
    foreach ($indexLocs as $child) {
      $sameBase = Verify::hostOf($child) === $base;
      if (!$sameBase) {
        $code = 1;
      }
      $this->say(sprintf('%s índice aponta para %s (base do ambiente: %s)', $sameBase ? 'PASS' : 'FAIL', $child, $base));
      $res = Verify::fetchWithHeaders($child);
      $locs = $res === NULL || Verify::statusCode($res['headers']) !== 200 ? NULL : Verify::xmlLocs($res['body']);
      if ($locs === NULL || $locs === []) {
        $this->say("FAIL $child: filho sem resposta 200 ou sem <loc>");
        $code = 1;
        continue;
      }
      foreach ($locs as $u) {
        $h = Verify::hostOf($u);
        if ($h !== NULL && !in_array($h, $allowed, TRUE)) {
          $this->say("FAIL $u: host fora da política de produção");
          $code = 1;
        }
        $contentHosts[$h] = TRUE;
      }
      $this->say(sprintf('PASS %s: %d URL(s) de conteúdo', $child, count($locs)));
    }
    foreach (array_keys($contentHosts) as $h) {
      // Check test mode hosts against their test equivalents.
      $target = $env === 'test' ? Verify::testEquivalent((string) $h) : (string) $h;
      $probe = Verify::fetchWithHeaders("https://$target/");
      $ok = $probe !== NULL && (Verify::statusCode($probe['headers']) ?? 500) < 400;
      $this->say(sprintf(
        '%s host de conteúdo %s (%s) responde %s',
        $ok ? 'PASS' : 'FAIL',
        $h,
        $target,
        $probe === NULL ? 'sem resposta' : (string) Verify::statusCode($probe['headers']),
      ));
      $code = $ok ? $code : 1;
    }
    $this->say("sitemap ($env): " . ($code === 0 ? 'PASS' : 'FAIL'));
    return $code;
  }

  /**
   * Reads the active environment from the neutral environment report.
   *
   * @return array{environment: string, site: string, changed_at: string}|null
   *   Environment data, or NULL when no environment has been selected.
   */
  private function currentEnvironment(): ?array {
    $file = $this->repoRoot . '/var/deployer/environment.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), TRUE) : NULL;
    return is_array($data)
      && ($data['schema'] ?? NULL) === 1
      && isset($data['environment'], $data['site'], $data['changed_at'])
      ? [
        'environment' => (string) $data['environment'],
        'site' => (string) $data['site'],
        'changed_at' => (string) $data['changed_at'],
      ]
      : NULL;
  }

  /**
   * Resolves the environment for a command, defaulting safely to test.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return string
   *   Selected environment name.
   */
  private function activeEnvironment(array $o): string {
    $sites = $this->json($this->toolRoot . '/config/deploy.json')['environments'] ?? [];
    if (is_string($o['env'] ?? NULL) && isset($sites[$o['env']])) {
      return (string) $o['env'];
    }
    $current = $this->currentEnvironment();
    return $current !== NULL && isset($sites[$current['environment']]) ? $current['environment'] : 'test';
  }

  /**
   * Displays or changes the selected site environment.
   *
   * @param string $sub
   *   Subcommand, either show or set.
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function environment(string $sub, array $o): int {
    if ($sub === '' || $sub === 'show') {
      $current = $this->currentEnvironment();
      $this->say($current === NULL
        ? 'environment: não definido (o Portal usa a configuração local)'
        : sprintf('environment: %s — %s (definido em %s)', $current['environment'], $current['site'], $current['changed_at']));
      return 0;
    }
    if ($sub !== 'set') {
      $this->err('uso: environment [show] | environment set --to=production|test');
      return 1;
    }
    $to = is_string($o['to'] ?? NULL) ? $o['to'] : '';
    $sites = $this->json($this->toolRoot . '/config/deploy.json')['environments'] ?? [];
    if (!isset($sites[$to])) {
      $this->err('environment: ambiente desconhecido: ' . $to);
      return 1;
    }
    $dir = $this->repoRoot . '/var/deployer';
    if (!is_dir($dir) && !mkdir($dir, 0750, TRUE)) {
      $this->err('environment: não foi possível criar var/deployer');
      return 1;
    }
    $data = ['schema' => 1, 'environment' => $to, 'site' => (string) $sites[$to]['site'], 'changed_at' => gmdate('c')];
    $target = $dir . '/environment.json';
    $temp = $dir . '/.environment-' . bin2hex(random_bytes(6)) . '.tmp';
    $fh = @fopen($temp, 'xb');
    if ($fh === FALSE) {
      $this->err('environment: não foi possível gravar');
      return 1;
    }
    chmod($temp, 0640);
    fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fclose($fh);
    if (!rename($temp, $target)) {
      @unlink($temp);
      $this->err('environment: falha ao publicar');
      return 1;
    }
    $this->say("environment: definido como {$to} — {$data['site']}");
    // Refresh the report after changing the environment.
    return $this->report([]);
  }

  /**
   * Writes the public-only robots result to the neutral indexing report.
   *
   * @param string $env
   *   Environment checked.
   * @param array<int, array{kind: string, url: string, ok: bool, observed: string}> $checks
   *   Results to store.
   * @param bool $ok
   *   Whether all checks passed.
   */
  private function writeIndexing(string $env, array $checks, bool $ok): void {
    $dir = $this->repoRoot . '/var/deployer';
    if (!is_dir($dir) && !mkdir($dir, 0750, TRUE)) {
      $this->err('robots: não foi possível criar a pasta de destino do resultado de indexação');
      return;
    }
    $data = Indexing::build($env, $checks, $ok, gmdate('c'));
    file_put_contents($dir . '/' . Indexing::FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
  }

  /**
   * Reads and decodes a JSON file when it exists.
   *
   * @param string $path
   *   Absolute or repository-relative path.
   *
   * @return mixed
   *   Decoded JSON value, or NULL when the file does not exist.
   */
  private function readJsonFile(string $path): mixed {
    return is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
  }

  /**
   * Writes the neutral status report consumed by the Portal.
   *
   * It contains names, counts, and states, never credential values.
   * Network checks remain separate commands.
   *
   * @param array<string, string|bool> $o
   *   Command options, including an optional output path.
   *
   * @return int
   *   Process exit code.
   */
  private function report(array $o): int {
    $out = is_string($o['out'] ?? NULL) ? $o['out'] : $this->repoRoot . '/var/deployer/status.json';
    $registry = $this->registry();
    $open = $registry->open();
    $boundary = $this->boundaryViolations();
    $report = [
      'schema' => 1,
      'tool' => 'aculta-deployer ' . self::VERSION,
      'generated_at' => gmdate('c'),
      'boundaries' => ['ok' => $boundary === [], 'violations' => count($boundary)],
      'registry' => [
        'open' => count($open),
        'open_blocking' => count($registry->openBlocking()),
        'entries' => array_map(static fn(array $e): array => [
          'id' => (string) ($e['id'] ?? ''),
          'kind' => (string) ($e['kind'] ?? ''),
          'blocking' => ($e['blocking'] ?? FALSE) === TRUE,
          'current' => (string) ($e['current'] ?? ''),
        ], $open),
      ],
      'environment' => $this->currentEnvironment(),
      'indexing' => Indexing::summarize($this->readJsonFile($this->repoRoot . '/var/deployer/' . Indexing::FILE)),
      'not_included' => ['network' => 'execute aculta-deployer sitemap e robots', 'values' => 'nunca incluídos'],
    ];
    $dir = dirname($out);
    if (!is_dir($dir) && !mkdir($dir, 0750, TRUE)) {
      $this->err('report: não foi possível criar a pasta de destino');
      return 1;
    }
    $temp = $dir . DIRECTORY_SEPARATOR . '.status-' . bin2hex(random_bytes(6)) . '.tmp';
    $fh = @fopen($temp, 'xb');
    if ($fh === FALSE) {
      $this->err('report: não foi possível gravar o relatório');
      return 1;
    }
    chmod($temp, 0640);
    fwrite($fh, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    fclose($fh);
    if (!rename($temp, $out)) {
      @unlink($temp);
      $this->err('report: falha ao publicar o relatório');
      return 1;
    }
    $this->say("report: relatório gravado em $out (sem valores de credenciais)");
    return 0;
  }

  /**
   * Verifies configured probes using read-only GET requests.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function verify(array $o): int {
    $env = $this->activeEnvironment($o);
    $code = 0;
    $checked = 0;
    foreach ($this->registry()->entries() as $e) {
      if (!isset($e['probe'], $e['expect'])) {
        continue;
      }
      // In test mode, resolved production probes are checked after deployment.
      if ($env === 'test' && ($e['status'] ?? '') === 'resolved') {
        $this->say("SKIP {$e['id']}: resolvida; sonda de produção é verificação pós-deploy");
        continue;
      }
      $checked++;
      // Map production probes to test hosts in test mode.
      $probe = $env === 'test' ? Verify::toTestEnvironment((string) $e['probe']) : (string) $e['probe'];
      $expect = $env === 'test' ? Verify::toTestEnvironment((string) $e['expect']) : (string) $e['expect'];
      $body = Verify::fetch($probe);
      if ($body === NULL) {
        $this->say("FAIL {$e['id']}: não foi possível ler {$probe} (URL inválida ou sem resposta)");
        $code = 1;
        continue;
      }
      if (Verify::evaluate($body, $expect)) {
        $this->say("PASS {$e['id']}: {$probe} contém o valor esperado ({$env})");
      }
      else {
        $this->say("FAIL {$e['id']}: {$probe} não contém o valor esperado ({$env})");
        $code = 1;
      }
    }
    $this->say($checked === 0 ? 'verify: nenhuma entrada com probe' : ($code === 0 ? 'verify: PASS' : 'verify: FAIL'));
    return $code;
  }

  /**
   * Adds a correction to the registry or imports a findings file.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function register(array $o): int {
    if (isset($o['from-json'])) {
      return $this->importFindings((string) $o['from-json']);
    }
    $required = ['kind', 'page', 'current', 'expected', 'reason', 'owner'];
    foreach ($required as $k) {
      if (!isset($o[$k]) || $o[$k] === TRUE || trim((string) $o[$k]) === '') {
        $this->err("register: --$k é obrigatório");
        return 1;
      }
    }
    try {
      $id = $this->registry()->add([
        'kind' => (string) $o['kind'],
        'page' => (string) $o['page'],
        'current' => (string) $o['current'],
        'expected_production' => (string) $o['expected'],
        'reason' => (string) $o['reason'],
        'owner' => (string) $o['owner'],
        'blocking' => isset($o['blocking']),
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $this->err('register: ' . $e->getMessage());
      return 1;
    }
    $this->say("registrado: $id");
    return 0;
  }

  /**
   * Imports a list of findings atomically from JSON.
   *
   * @param string $path
   *   Path to a regular JSON file.
   *
   * @return int
   *   Process exit code.
   */
  private function importFindings(string $path): int {
    if ($path === '' || !is_file($path) || is_link($path)) {
      $this->err('register: --from-json precisa apontar para um arquivo regular');
      return 1;
    }
    if (filesize($path) > 262144) {
      $this->err('register: arquivo de achados acima de 256 KiB');
      return 1;
    }
    try {
      $findings = json_decode((string) file_get_contents($path), TRUE, 64, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      $this->err('register: JSON de achados inválido');
      return 1;
    }
    if (!is_array($findings) || !array_is_list($findings)) {
      $this->err('register: o arquivo deve conter uma lista de achados');
      return 1;
    }
    try {
      $ids = $this->registry()->addMany($findings);
    }
    catch (\InvalidArgumentException $e) {
      $this->err('register: nenhum achado foi gravado: ' . $e->getMessage());
      return 1;
    }
    $this->say('importados: ' . implode(', ', $ids));
    return 0;
  }

  /**
   * Builds a deployment tree for the selected target.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function build(array $o): int {
    $target = is_string($o['target'] ?? NULL) ? $o['target'] : 'production';
    if (!in_array($target, ['production', 'test'], TRUE)) {
      $this->err('build: --target aceita production ou test');
      return 1;
    }
    if (is_string($o['env'] ?? NULL) && $o['env'] !== $target) {
      $this->err("build: --env={$o['env']} diverge de --target=$target");
      return 1;
    }
    $out = $o['out'] ?? NULL;
    if (!is_string($out) || $out === '') {
      $this->err('build: --out=DIR é obrigatório');
      return 1;
    }
    $outAbs = $this->absolute($out);
    // Resolve ancestor symlinks before checking the repository boundary.
    $probe = $outAbs;
    while (!file_exists($probe) && dirname($probe) !== $probe) {
      $probe = dirname($probe);
    }
    $real = realpath($probe) ?: $probe;
    $repoReal = realpath($this->repoRoot) ?: $this->repoRoot;
    if ($real === $repoReal || str_starts_with($real . '/', $repoReal . '/')) {
      $this->err('build: o diretório de saída não pode ficar dentro do repositório (inclusive via link)');
      return 1;
    }
    // Builds never overwrite existing output directories.
    if (file_exists($outAbs) && (!is_dir($outAbs) || count(scandir($outAbs)) > 2)) {
      $this->err(
        'build: o diretório de saída já existe e não está vazio; '
        . 'use um diretório novo (builds anteriores são preservados)',
      );
      return 1;
    }
    $blocking = $this->registry()->openBlocking();
    if ($target === 'production' && $blocking !== [] && !isset($o['allow-open-blocking'])) {
      $this->err(
        'build: existem entradas bloqueantes abertas; resolva-as ou '
        . 'use --allow-open-blocking para um build de ensaio',
      );
      foreach ($blocking as $e) {
        $this->err("  {$e['id']} ({$e['kind']}): {$e['page']}");
      }
      return 2;
    }
    $transform = $this->transform();
    if ($transform->validate() !== [] || $transform->validateProfiles() !== []) {
      $this->err('build: configuração de escopo ou de perfis inválida; rode check para detalhes');
      return 1;
    }
    $maxBytes = (int) ($this->json($this->toolRoot . '/config/deploy.json')['max_bytes'] ?? 2097152);
    $robots = $this->json($this->toolRoot . '/config/deploy.json')['robots_policy']['production'] ?? [];
    if ($target === 'production' && Verify::isNoindex($robots['x_robots_tag'] ?? NULL)) {
      $this->err('build: a política de produção envia noindex; o build de produção não é gerado');
      return 1;
    }
    $report = [
      'tool' => 'aculta-deployer ' . self::VERSION,
      'target' => $target,
      'files' => [],
      'dropped' => [],
      'skipped_binary' => [],
      'replacements' => 0,
      'profile_rules' => 0,
    ];
    if (!is_dir($outAbs) && !mkdir($outAbs, 0775, TRUE)) {
      $this->err('build: não foi possível criar o diretório de saída');
      return 1;
    }
    try {
      foreach ($this->scopeFiles() as $rel) {
        if ($target === 'production' && $transform->isDropped($rel)) {
          $report['dropped'][] = $rel;
          continue;
        }
        $src = $this->repoRoot . '/' . $rel;
        if (filesize($src) > $maxBytes) {
          throw new \RuntimeException("arquivo acima do limite de $maxBytes bytes: $rel");
        }
        $raw = (string) file_get_contents($src);
        if (str_contains($raw, "\0")) {
          // Binário: não é reescrito. Se contiver host de teste, o build falha.
          if ($transform->countTestHosts($raw) > 0) {
            throw new \RuntimeException("binário com host de teste no escopo: $rel");
          }
          $content = $raw;
          $count = 0;
          $report['skipped_binary'][] = $rel;
        }
        elseif ($target === 'production') {
          [$content, $count] = $transform->applyHosts($raw);
        }
        else {
          $content = $raw;
          $count = 0;
        }
        $profileRules = $transform->profileRules($target, $rel);
        if ($profileRules !== []) {
          $content = $transform->applyProfile($content, $profileRules, $rel);
          $report['profile_rules'] += count($profileRules);
        }
        $file = $outAbs . '/' . $rel;
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0775, TRUE)) {
          throw new \RuntimeException("não foi possível criar diretório para $rel");
        }
        file_put_contents($file, $content);
        $report['files'][] = ['path' => $rel, 'replacements' => $count, 'sha256' => hash('sha256', $content)];
        $report['replacements'] += $count;
      }
      // No test mode, no test host can remain in generated production files.
      foreach ($target === 'production' ? $report['files'] : [] as $f) {
        if (in_array($f['path'], $report['skipped_binary'], TRUE)) {
          continue;
        }
        if ($transform->countTestHosts((string) file_get_contents($outAbs . '/' . $f['path'])) > 0) {
          throw new \RuntimeException('host de teste restou no arquivo gerado: ' . $f['path']);
        }
      }
    }
    catch (\RuntimeException $e) {
      $this->removeTree($outAbs);
      $this->err('build: ' . $e->getMessage() . ' (saída removida)');
      return 1;
    }
    $this->writeAtomic($outAbs . '/deploy-policy.json', json_encode(['robots' => $robots], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $this->writeAtomic($outAbs . '/deploy-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $this->say(sprintf('build (%s): %d arquivos gravados, %d removidos, %d binários preservados, %d substituições de host, %d regras de perfil em %s',
      $target, count($report['files']), count($report['dropped']), count($report['skipped_binary']), $report['replacements'], $report['profile_rules'], $out));
    return 0;
  }

  /**
   * Creates a read-only environment transition plan.
   *
   * The plan lists config/sync changes and rollback steps without modifying a
   * Runtime.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function transition(array $o): int {
    $target = is_string($o['target'] ?? NULL) ? $o['target'] : '';
    if (!in_array($target, ['production', 'test'], TRUE)) {
      $this->err('transition: informe --target=production ou --target=test');
      return 1;
    }
    $out = $o['out'] ?? NULL;
    if (!is_string($out) || $out === '') {
      $this->err('transition: --out=DIR é obrigatório');
      return 1;
    }
    $buildOptions = ['target' => $target, 'out' => $out];
    if (isset($o['allow-open-blocking'])) {
      $buildOptions['allow-open-blocking'] = TRUE;
    }
    if ($this->build($buildOptions) !== 0) {
      return 1;
    }
    $outAbs = $this->absolute($out);
    $changed = [];
    foreach (glob($outAbs . '/config/sync/*.yml') ?: [] as $file) {
      $rel = 'config/sync/' . basename($file);
      $repo = $this->repoRoot . '/' . $rel;
      if (!is_file($repo) || file_get_contents($repo) !== file_get_contents($file)) {
        $changed[] = $rel;
      }
    }
    sort($changed);
    $plan = [
      'tool' => 'aculta-deployer ' . self::VERSION,
      'target' => $target,
      'changed_files' => $changed,
      'steps' => [
        'backup' => 'exportar a configuração ativa do Runtime para DIR_BACKUP (config:export do Drupal; para rollback)',
        'import' => 'copie somente changed_files para DIR_IMPORT e importe com config:import --partial --source=DIR_IMPORT (Drupal)',
        'caches' => 'limpar caches do Drupal (cr)',
        'sitemap' => $target === 'test' ? 'regenerar o simple_sitemap (simple-sitemap:generate); aculta-deployer sitemap --env=test' : 'após o deploy: aculta-deployer sitemap --env=production',
        'rollback' => 'importar novamente o backup com config:import --partial --source=DIR_BACKUP e limpar caches',
      ],
      'not_done' => 'nenhum Runtime foi alterado por este comando',
    ];
    $this->writeAtomic($outAbs . '/transition-plan.json', json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $this->say(sprintf(
      'transition (%s): %d arquivo(s) de config/sync mudam; plano em %s/transition-plan.json',
      $target,
      count($changed),
      $out,
    ));
    foreach ($changed as $rel) {
      $this->say("  $rel");
    }
    return 0;
  }

  /**
   * Prepares public_html to serve the Drupal web directory.
   *
   * The command only simulates unless --apply is provided.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function hostingerLayout(array $o): int {
    $domainRoot = $o['domain-root'] ?? NULL;
    if (!is_string($domainRoot) || $domainRoot === '' || $domainRoot[0] !== '/') {
      $this->err('hostinger-layout: --domain-root absoluto é obrigatório (pasta do domínio na Hostinger)');
      return 1;
    }
    $layout = new HostingerLayout();
    try {
      $plan = $layout->inspect($domainRoot);
      $this->say('instalação: ' . $plan['installation_root']);
      $this->say('raiz pública: ' . $plan['installation_root'] . '/web');
      $this->say('public_html: ' . $plan['status']);
      if (!isset($o['apply'])) {
        $this->say('simulação: nenhuma alteração feita; use --apply para ativar o symlink');
        return 0;
      }
      $this->say($layout->activate($plan));
      return 0;
    }
    catch (\RuntimeException $e) {
      $this->err('hostinger-layout: ' . $e->getMessage());
      return 1;
    }
  }

  /**
   * Writes a file atomically using a temporary sibling and rename.
   *
   * @param string $target
   *   Destination path.
   * @param string $content
   *   File contents.
   */
  private function writeAtomic(string $target, string $content): void {
    $temp = dirname($target) . '/.' . basename($target) . '-' . bin2hex(random_bytes(6)) . '.tmp';
    $written = file_put_contents($temp, $content, LOCK_EX);
    if ($written !== strlen($content) || !rename($temp, $target)) {
      @unlink($temp);
      throw new \RuntimeException('Não foi possível gravar ' . basename($target) . '.');
    }
  }

  /**
   * Removes a directory tree and its contents.
   *
   * @param string $dir
   *   Directory path.
   */
  private function removeTree(string $dir): void {
    if (!is_dir($dir)) {
      return;
    }
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
      $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
  }

  /**
   * Finds violations of configured dependency boundaries.
   *
   * @return string[]
   *   Boundary violations.
   */
  private function boundaryViolations(): array {
    $rules = $this->json($this->toolRoot . '/config/boundary.json');
    return (new Boundary($this->repoRoot, $this->toolRoot, $rules))->check();
  }

  /**
   * Finds fixed project email addresses outside environment_bound.
   *
   * @return array<string, string>
   *   Relative file path mapped to its first matching email address.
   */
  private function unboundEnvironmentValues(): array {
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    $bound = array_map(static fn(array $e): string => (string) ($e['file'] ?? ''), $config['environment_bound'] ?? []);
    $found = [];
    foreach ($this->scopeFiles() as $rel) {
      if (in_array($rel, $bound, TRUE)) {
        continue;
      }
      $raw = (string) file_get_contents($this->repoRoot . '/' . $rel);
      if (preg_match('/[A-Za-z0-9._%+-]+@(?:aculta|toca)\.[a-z.]+/', $raw, $m) === 1) {
        $found[$rel] = $m[0];
      }
    }
    return $found;
  }

  /**
   * Finds profile rules that do not match exactly once in config/sync.
   *
   * @return string[]
   *   Dead, ambiguous, or invalid profile rules.
   */
  private function profileRuleFailures(Transform $transform): array {
    $errors = [];
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    foreach (array_keys($config['profiles'] ?? []) as $target) {
      foreach ($config['profiles'][$target]['rules'] ?? [] as $i => $rule) {
        $file = $this->repoRoot . '/' . ($rule['file'] ?? '');
        if (!is_file($file)) {
          $errors[] = "$target regra $i: arquivo inexistente {$rule['file']}";
          continue;
        }
        try {
          $transform->applyProfile(
            (string) file_get_contents($file),
            [['pattern' => (string) $rule['pattern'], 'with' => (string) $rule['with']]],
            (string) $rule['file'],
          );
        }
        catch (\RuntimeException $e) {
          $errors[] = "$target regra $i: " . $e->getMessage();
        }
      }
    }
    return $errors;
  }

  /**
   * Creates the configured transformation service.
   *
   * @return \AcultaDeployer\Transform
   *   Transformation service.
   */
  private function transform(): Transform {
    return new Transform($this->json($this->toolRoot . '/config/deploy.json'));
  }

  /**
   * Loads the deployment correction registry.
   *
   * @return \AcultaDeployer\Registry
   *   Registry service.
   */
  private function registry(): Registry {
    return new Registry($this->toolRoot . '/registry/deploy-registry.json');
  }

  /**
   * Lists repository-relative files included in deployment builds.
   *
   * @return string[]
   *   Files within the configured build scope.
   */
  private function scopeFiles(): array {
    $files = [];
    foreach (['config/sync', 'scripts/content'] as $dir) {
      $abs = $this->repoRoot . '/' . $dir;
      if (!is_dir($abs)) {
        continue;
      }
      $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS));
      foreach ($it as $f) {
        if ($f->isFile() && !$f->isLink()) {
          $rel = ltrim(str_replace($this->repoRoot, '', $f->getPathname()), '/');
          if ($this->transform()->inScope($rel)) {
            $files[] = $rel;
          }
        }
      }
    }
    sort($files);
    return $files;
  }

  /**
   * Scans build-scope files and documentation for host counts.
   *
   * @param \AcultaDeployer\Transform $transform
   *   Rules used to detect and classify hosts.
   *
   * @return array{scope: int, with_test_host: int, dropped: int, outside_scope: string[]}
   *   Scan counters and out-of-scope documentation paths.
   */
  private function scan(Transform $transform): array {
    $scope = $this->scopeFiles();
    $withTest = 0;
    $dropped = 0;
    foreach ($scope as $rel) {
      if ($transform->isDropped($rel)) {
        $dropped++;
      }
      if ($transform->countTestHosts((string) file_get_contents($this->repoRoot . '/' . $rel)) > 0) {
        $withTest++;
      }
    }
    $outside = [];
    $docs = $this->repoRoot . '/docs';
    if (is_dir($docs)) {
      $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docs, \FilesystemIterator::SKIP_DOTS));
      foreach ($it as $f) {
        if ($f->isFile() && $transform->countTestHosts((string) file_get_contents($f->getPathname())) > 0) {
          $outside[] = ltrim(str_replace($this->repoRoot, '', $f->getPathname()), '/');
        }
      }
    }
    return ['scope' => count($scope), 'with_test_host' => $withTest, 'dropped' => $dropped, 'outside_scope' => $outside];
  }

  /**
   * Checks webhook endpoints and secret declarations without writing.
   *
   * Does not call the provider or read secret values.
   *
   * @return int
   *   Process exit code.
   */
  private function webhooks(): int {
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    $environments = $config['environments'] ?? [];
    $hooks = $config['external_webhooks'] ?? NULL;
    if (!is_array($hooks) || $hooks === []) {
      $this->err('webhooks: external_webhooks ausente ou vazio em config/deploy.json');
      return 1;
    }
    $contract = $this->json($this->repoRoot . '/config/secrets-contract.json')['variables'] ?? [];
    $code = 0;
    foreach ($hooks as $name => $hook) {
      if (!is_array($hook) || !isset($hook['path'], $hook['auth']['secret'])) {
        continue;
      }
      $secret = (string) $hook['auth']['secret'];
      $variable = NULL;
      foreach ($contract as $item) {
        if (($item['name'] ?? NULL) === $secret) {
          $variable = $item;
        }
      }
      foreach ($hook['environments'] ?? [] as $env => $spec) {
        $site = $environments[$env]['site'] ?? NULL;
        if ($site === NULL) {
          $this->err("webhooks: $name: ambiente $env sem environments.$env.site");
          $code = 1;
          continue;
        }
        $url = rtrim($site, '/') . $hook['path'];
        $required = $variable !== NULL && in_array($env, (array) ($variable['required_in'] ?? []), TRUE);
        $inContract = $variable !== NULL && in_array($env, (array) ($variable['environments'] ?? []), TRUE);
        if (!$inContract) {
          $this->err("webhooks: $name/$env: segredo $secret não declarado para o ambiente $env no contrato");
          $code = 1;
          continue;
        }
        $state = $spec['status'] ?? 'indefinido';
        $this->say(sprintf('%s/%s: %s, %s; segredo %s (%s); estado: %s', $name, $env, $hook['type'] ?? '-', $url, $secret, $required ? 'obrigatório' : 'opcional', $state));
        if ($state === 'pendente') {
          $this->say("  ação no deploy: " . ($spec['action'] ?? '(sem ação declarada)'));
        }
        if ($env === 'production' && $state === 'pendente' && !$required) {
          $this->err("webhooks: $name/production: segredo $secret deve ser obrigatório em produção (required_in) para o cadastro do deploy");
          $code = 1;
        }
      }
    }
    return $code;
  }

  /**
   * Provisions missing secrets and a Brevo webhook for an environment.
   *
   * The default is dry-run. Applying production changes requires explicit
   * confirmation; secret values are never printed.
   *
   * @param array<string, string|bool> $o
   *   Command options.
   *
   * @return int
   *   Process exit code.
   */
  private function provision(array $o): int {
    $env = (string) ($o['env'] ?? '');
    $secrets = (string) ($o['secrets'] ?? '');
    $apply = !empty($o['apply']);
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    $site = $config['environments'][$env]['site'] ?? NULL;
    $hook = $config['external_webhooks']['brevo_transactional'] ?? NULL;
    if ($site === NULL || !is_array($hook)) {
      $this->err('provision: ambiente desconhecido ou webhook não declarado em external_webhooks');
      return 1;
    }
    if ($secrets === '' || !is_file($secrets)) {
      $this->err('provision: informe --secrets=CAMINHO do arquivo de segredos do ambiente');
      return 1;
    }
    if ($apply && $env === 'production' && empty($o['confirm-production'])) {
      $this->err('provision: produção exige --confirm-production além de --apply');
      return 1;
    }
    $url = rtrim((string) $site, '/') . $hook['path'];
    $contract = $this->json($this->repoRoot . '/config/secrets-contract.json')['variables'] ?? [];
    $required = [];
    foreach (Provision::GENERATED as $name => $format) {
      foreach ($contract as $item) {
        if (($item['name'] ?? NULL) === $name && in_array($env, (array) ($item['required_in'] ?? []), TRUE)) {
          $required[] = $name;
        }
      }
    }
    $missing = Provision::missing(Provision::namesIn($secrets), $required);
    $apiKey = Provision::valueOf($secrets, 'BREVO_API_KEY');
    if ($apiKey === NULL || $apiKey === '') {
      $this->err('provision: BREVO_API_KEY ausente no arquivo de segredos');
      return 1;
    }
    $list = $this->brevoCall('GET', '/v3/webhooks?type=transactional', $apiKey);
    if ($list['http'] !== 200) {
      $this->err('provision: não foi possível listar os webhooks do Brevo (HTTP ' . $list['http'] . ')');
      return 1;
    }
    $found = NULL;
    foreach ($list['body']['webhooks'] ?? [] as $hookRow) {
      if (($hookRow['url'] ?? NULL) === $url) {
        $found = $hookRow;
      }
    }
    $testId = isset($o['remove-test']) && is_numeric($o['remove-test']) ? (int) $o['remove-test'] : NULL;
    $tokenChanged = in_array('BREVO_WEBHOOK_TOKEN', $missing, TRUE);
    foreach (Provision::plan($missing, $found, $tokenChanged, $testId) as $action) {
      $this->say(($apply ? 'executar: ' : 'plano: ') . $action);
    }
    if (!$apply) {
      return 0;
    }

    $values = [];
    foreach ($missing as $name) {
      $values[$name] = Provision::generate($name);
    }
    if ($values !== []) {
      Provision::append($secrets, $values);
      $this->say('ok: segredos gravados: ' . implode(', ', array_keys($values)));
    }
    $token = Provision::valueOf($secrets, 'BREVO_WEBHOOK_TOKEN');
    $auth = ['type' => 'bearer', 'token' => (string) $token];
    if ($found === NULL) {
      $created = $this->brevoCall('POST', '/v3/webhooks', $apiKey, [
        'url' => $url,
        'description' => (string) ($hook['purpose'] ?? 'ACULTA Mailer'),
        'type' => 'transactional',
        'events' => $hook['events'],
        'auth' => $auth,
      ]);
      if ($created['http'] !== 201) {
        $this->err('provision: falha ao cadastrar o webhook (HTTP ' . $created['http'] . ')');
        return 1;
      }
      $this->say('ok: webhook cadastrado, id ' . ($created['body']['id'] ?? '?') . '. Atualize registration_id em deploy.json.');
    }
    elseif ($tokenChanged) {
      $updated = $this->brevoCall('PUT', '/v3/webhooks/' . $found['id'], $apiKey, [
        'url' => $url,
        'description' => (string) ($hook['purpose'] ?? 'ACULTA Mailer'),
        'events' => $hook['events'],
        'auth' => $auth,
      ]);
      if ($updated['http'] >= 300) {
        $this->err('provision: falha ao atualizar o token do webhook (HTTP ' . $updated['http'] . ')');
        return 1;
      }
      $this->say('ok: token do webhook ' . $found['id'] . ' atualizado.');
    }
    if ($testId !== NULL) {
      $deleted = $this->brevoCall('DELETE', '/v3/webhooks/' . $testId, $apiKey);
      if ($deleted['http'] !== 204) {
        $this->err('provision: falha ao remover o cadastro de teste ' . $testId . ' (HTTP ' . $deleted['http'] . ')');
        return 1;
      }
      $this->say('ok: cadastro de teste ' . $testId . ' removido.');
    }
    return 0;
  }

  /**
   * Calls the Brevo API with the key held only in memory.
   *
   * @return array{http: int, body: array}
   *   HTTP status and decoded response body.
   */
  private function brevoCall(string $method, string $path, string $apiKey, ?array $payload = NULL): array {
    $handle = curl_init('https://api.brevo.com' . $path);
    $headers = ['api-key: ' . $apiKey, 'accept: application/json'];
    if ($payload !== NULL) {
      $headers[] = 'content-type: application/json';
    }
    curl_setopt_array($handle, [
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 30,
    ]);
    if ($payload !== NULL) {
      curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $raw = curl_exec($handle);
    $http = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $body = is_string($raw) ? json_decode($raw, TRUE) : NULL;
    return ['http' => $http, 'body' => is_array($body) ? $body : []];
  }

  /**
   * Checks each environment's session cookie against its services file.
   *
   * @return int
   *   Process exit code.
   */
  private function cookies(): int {
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    $environments = $config['session_cookie']['environments'] ?? NULL;
    if (!is_array($environments) || $environments === []) {
      $this->err('cookies: session_cookie.environments ausente ou vazio em config/deploy.json');
      return 1;
    }
    $declared = [];
    $code = 0;
    foreach ($environments as $name => $spec) {
      $expected = $spec['cookie_domain'] ?? '';
      $declared[] = $expected;
      $source = $this->repoRoot . '/' . ($spec['source'] ?? '');
      $check = $source;
      $label = 'arquivo';
      if (!is_file($source)) {
        // Check the versioned model when a production file is absent.
        $check = $this->repoRoot . '/' . ($spec['source'] ?? '') . '.example';
        $label = 'modelo';
        if (!is_file($check)) {
          $this->err("cookies: $name sem arquivo nem modelo ({$spec['source']})");
          $code = 1;
          continue;
        }
      }
      $found = preg_match("/^\s*cookie_domain:\s*['\"]?([^'\"\s]+)['\"]?\s*$/m", (string) file_get_contents($check), $m) === 1 ? $m[1] : NULL;
      if ($found === $expected) {
        $this->say("ok $name: $label {$spec['source']} define cookie_domain $expected");
      }
      else {
        $this->err("cookies: $name diverge: $label define " . ($found ?? '(ausente)') . ", política declarada $expected");
        $code = 1;
      }
    }
    // Arquivos de serviços versionados com cookie_domain fora da política.
    foreach (glob($this->repoRoot . '/web/sites/default/services*.yml') ?: [] as $file) {
      if (preg_match("/^\s*cookie_domain:\s*['\"]?([^'\"\s]+)/m", (string) file_get_contents($file), $m) === 1 && !in_array($m[1], $declared, TRUE)) {
        $this->say('dívida ' . basename($file) . ": cookie_domain {$m[1]} não corresponde a nenhum ambiente declarado (ver docs/COOKIES-AMBIENTE.md)");
      }
    }
    return $code;
  }

  /**
   * Checks the domain transition table against config/sync.
   *
   * @return int
   *   Process exit code.
   */
  private function domains(): int {
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    $table = $config['domain_transition']['entries'] ?? NULL;
    if (!is_array($table) || $table === []) {
      $this->err('domains: domain_transition.entries ausente ou vazio em config/deploy.json');
      return 1;
    }
    $transform = $this->transform();
    $aligned = 0;
    $pending = 0;
    foreach ($table as $entry) {
      $id = $entry['domain_id'] ?? '';
      $test = $entry['test_host'] ?? '';
      $prod = $entry['production_host'] ?? '';
      if (!is_string($id) || $id === '' || !is_string($test) || $test === '' || !is_string($prod) || $prod === '') {
        $this->err('domains: entrada incompleta (domain_id, test_host e production_host são obrigatórios)');
        return 1;
      }
      [$byRule] = $transform->applyHosts($test);
      $rule = $byRule === $prod ? 'regra de host' : "exceção declarada (regra daria $byRule)";
      $record = $this->repoRoot . '/config/sync/domain.record.' . $id . '.yml';
      $current = is_file($record) && preg_match('/^hostname: (.+)$/m', (string) file_get_contents($record), $m) === 1 ? trim($m[1]) : NULL;
      if ($current === NULL) {
        $pending++;
        $this->say("pendente $id: sem registro de domínio em config/sync (destino $prod)");
      }
      elseif ($current === $prod) {
        $aligned++;
        $this->say("ok $id: config/sync = $prod ($rule)");
      }
      else {
        $pending++;
        $this->say("pendente $id: config/sync diz $current; destino de produção $prod ($rule); muda na troca de domínio no Alpha");
      }
    }
    $this->say(sprintf('domains: %d alinhado(s), %d pendente(s) de Alpha; somente leitura', $aligned, $pending));
    return 0;
  }

  /**
   * Reads and decodes a JSON file.
   *
   * @param string $path
   *   File path.
   *
   * @return array<string, mixed>
   *   Decoded JSON data.
   */
  private function json(string $path): array {
    return json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Resolves a path relative to the current working directory.
   *
   * @param string $path
   *   Relative or absolute path.
   *
   * @return string
   *   Absolute path.
   */
  private function absolute(string $path): string {
    return str_starts_with($path, '/') ? rtrim($path, '/') : rtrim(getcwd() . '/' . $path, '/');
  }

  /**
   * Parses long command-line options.
   *
   * @param string[] $args
   *   Raw command arguments.
   *
   * @return array<string, string|bool>
   *   Parsed option names and values.
   */
  private function options(array $args): array {
    $out = [];
    foreach ($args as $a) {
      if (str_starts_with($a, '--')) {
        [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, TRUE);
        $out[$k] = $v;
      }
    }
    return $out;
  }

  /**
   * Prints command usage information.
   *
   * @return int
   *   Success exit code.
   */
  private function help(): int {
    $this->say(<<<TXT
aculta-deployer {version} — substitui hosts de teste (*.aculta.toca.net.br) por produção (*.aculta.org) no build de deploy.

Uso:
  aculta-deployer check [--strict]     valida registro, fronteiras e escopo; --strict falha com bloqueantes abertas
  aculta-deployer boundaries           verifica a separação com Portal e tema
  aculta-deployer list                 lista as correções de deploy registradas
  aculta-deployer register --kind=K --page=P --current=C --expected=E --reason=R --owner=O [--blocking]
  aculta-deployer build --out=DIR [--allow-open-blocking]
  aculta-deployer hostinger-layout --domain-root=DIR [--apply]
  aculta-deployer robots [--env=production|test]  GET somente leitura: X-Robots-Tag, caminhos privados e robots.txt (Sitemap)
  aculta-deployer environment [show] | environment set --to=production|test  define o ambiente do site (e o endereço) lido pelo Portal
  aculta-deployer provision --env=production|test --secrets=CAMINHO [--apply] [--confirm-production] [--remove-test=ID]
                                       gera segredos que faltam, cadastra ou atualiza o webhook do Brevo; dry-run sem --apply
  aculta-deployer webhooks              leitura: confere os webhooks de external_webhooks (URL por ambiente, segredo no contrato e estado do cadastro)
  aculta-deployer cookies               leitura: confere o cookie de sessão por ambiente (session_cookie) contra services.*.yml
  aculta-deployer domains              GET/leitura: confere a tabela domain_transition (teste → produção) contra config/sync
  aculta-deployer report [--out=PATH]  relatório neutro para o painel do Portal (sem valores)
  aculta-deployer sitemap [--env=production|test]  GET somente leitura: índice, filhos e hosts de conteúdo (cross-host)
  aculta-deployer version

hostinger-layout valida a instalação na raiz da pasta do domínio e, com --apply, substitui
public_html existente pelo symlink relativo public_html -> web e exclui seu conteúdo anterior.

Códigos de saída: 0 sucesso; 1 erro de uso, validação ou fronteira; 2 bloqueado (entrada bloqueante aberta).

Guia completo: o submódulo aculta_deployer/docs/USO.md
TXT . "\n", ['{version}' => self::VERSION]);
    return 0;
  }

  /**
   * Writes an informational line to standard output.
   *
   * @param string $line
   *   Line to print.
   *
   * @return int
   *   Success exit code.
   */
  private function say(string $line): int {
    fwrite(STDOUT, $line . "\n");
    return 0;
  }

  /**
   * Writes an error line to standard error.
   *
   * @param string $line
   *   Line to print.
   */
  private function err(string $line): void {
    fwrite(STDERR, $line . "\n");
  }

}
