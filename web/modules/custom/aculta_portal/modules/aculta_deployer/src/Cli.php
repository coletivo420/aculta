<?php

declare(strict_types=1);

namespace AcultaDeployer;

/** Comandos da ferramenta. Não usa Drupal, Drush nem vendor. */
final class Cli {

  public const VERSION = '0.1.25';

  private readonly string $toolRoot;
  private readonly string $repoRoot;

  public function __construct(string $toolRoot) {
    $this->toolRoot = $toolRoot;
    // Submódulo em web/modules/custom/aculta_portal/modules/aculta_deployer: seis níveis até a raiz.
    $this->repoRoot = dirname($toolRoot, 6);
  }

  /** @param string[] $argv */
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
      'verify' => $this->verify($options),
      'robots' => $this->robots($options),
      'sitemap' => $this->sitemap($options),
      'report' => $this->report($options),
      'environment' => $this->environment((string) ($argv[2] ?? ''), $options),
      default => $this->help(),
    };
  }

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
    // Guardrail de ambiente: arquivos que mudam entre teste e produção precisam de perfil em todos os ambientes.
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
    // Barreira: cookie de sessão por ambiente tem de bater com config/deploy.json (session_cookie).
    if ($this->cookies() !== 0) {
      $code = 1;
    }
    return $code;
  }

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

  private function list(): int {
    foreach ($this->registry()->entries() as $e) {
      $this->say(sprintf('%s [%s] %s%s — %s', $e['id'], $e['status'], $e['kind'], ($e['blocking'] ?? false) ? ' (bloqueante)' : '', $e['page']));
    }
    return 0;
  }

  /**
   * Política de indexação por ambiente: produção indexável em todos os hosts;
   * teste com noindex. GET somente leitura.
   *
   * @param array<string, string|bool> $o
   */
  private function robots(array $o): int {
    $env = $this->activeEnvironment($o);
    $policy = $this->json($this->toolRoot . '/config/deploy.json')['robots_policy'][$env] ?? null;
    if ($policy === null) {
      $this->err("robots: ambiente desconhecido: $env");
      return 1;
    }
    $code = 0;
    $checks = [];
    foreach ($policy['hosts'] as $url) {
      $res = Verify::fetchWithHeaders((string) $url);
      if ($res === null) {
        $this->say("FAIL $url: sem resposta");
        $checks[] = ['kind' => 'host', 'url' => (string) $url, 'ok' => false, 'observed' => 'sem resposta'];
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
        if ($res === null || Verify::statusCode($res['headers']) !== 200) {
          $this->say("FAIL $url robots.txt: sem resposta 200");
          $checks[] = ['kind' => 'robots_txt', 'url' => (string) $url, 'ok' => false, 'observed' => 'sem resposta 200'];
          $code = 1;
          continue;
        }
        $advertised = in_array($indexUrl, Verify::sitemapDirectives($res['body']), true);
        $blocked = Verify::disallowsRoot($res['body']);
        $ok = $advertised && !$blocked;
        $this->say(sprintf('%s %s robots.txt: Sitemap=%s, Disallow: / %s', $ok ? 'PASS' : 'FAIL', $url, $advertised ? 'índice' : 'ausente', $blocked ? 'presente' : 'ausente'));
        $checks[] = ['kind' => 'robots_txt', 'url' => (string) $url, 'ok' => $ok, 'observed' => ($advertised ? 'Sitemap índice' : 'Sitemap ausente') . ', Disallow: / ' . ($blocked ? 'presente' : 'ausente')];
        $code = $ok ? $code : 1;
      }
    }
    foreach ($policy['private_probes'] ?? [] as $url) {
      $res = Verify::fetchWithHeaders((string) $url);
      if ($res === null) {
        $this->say("FAIL $url: sem resposta (caminho privado)");
        $checks[] = ['kind' => 'private', 'url' => (string) $url, 'ok' => false, 'observed' => 'sem resposta'];
        $code = 1;
        continue;
      }
      $value = Verify::robotsHeader($res['headers']);
      $status = Verify::statusCode($res['headers']);
      $meta = Verify::metaNoindex($res['body']);
      // Privado: noindex no cabeçalho ou no HTML, ou status que não expõe conteúdo.
      $ok = Verify::isNoindex($value) || $meta || Verify::isRefusedStatus($status);
      $this->say(sprintf('%s %s (privado): status=%s X-Robots-Tag=%s meta=%s',
        $ok ? 'PASS' : 'FAIL', $url, $status ?? '?', $value ?? '(ausente)', $meta ? 'noindex' : 'ausente'));
      $checks[] = ['kind' => 'private', 'url' => (string) $url, 'ok' => $ok, 'observed' => 'status ' . ($status ?? '?') . ', X-Robots-Tag ' . ($value ?? 'ausente') . ', meta ' . ($meta ? 'noindex' : 'ausente')];
      $code = $ok ? $code : 1;
    }
    $this->writeIndexing($env, $checks, $code === 0);
    $this->say("robots ($env): " . ($code === 0 ? 'PASS' : 'FAIL'));
    return $code;
  }

  /**
   * Descoberta e sitemaps por ambiente (GET somente leitura):
   * 1) o índice responde e cada filho está na base do ambiente (sem host de teste
   *    em produção, e sem host de produção no servidor de testes além do canônico);
   * 2) as URLs dos filhos pertencem aos hosts de produção da política e esses hosts
   *    respondem (cross-host, por exemplo apoio.aculta.org).
   *
   * @param array<string, string|bool> $o
   */
  private function sitemap(array $o): int {
    $env = $this->activeEnvironment($o);
    $cfg = $this->json($this->toolRoot . '/config/deploy.json');
    $policy = $cfg['sitemap'][$env] ?? null;
    if ($policy === null) {
      $this->err("sitemap: ambiente desconhecido: $env");
      return 1;
    }
    // A política de hosts é a de produção; no teste, cada host permitido é comparado pelo equivalente de teste.
    $allowed = [];
    foreach ($cfg['robots_policy']['production']['hosts'] ?? [] as $url) {
      $host = (string) Verify::hostOf((string) $url);
      $allowed[] = $env === 'test' ? Verify::testEquivalent($host) : $host;
    }
    $base = Verify::hostOf((string) $policy['index_base']);
    $code = 0;
    $index = Verify::fetchWithHeaders((string) $policy['index_url']);
    $indexLocs = $index === null ? null : Verify::xmlLocs($index['body']);
    if ($indexLocs === null || $indexLocs === []) {
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
      $locs = $res === null || Verify::statusCode($res['headers']) !== 200 ? null : Verify::xmlLocs($res['body']);
      if ($locs === null || $locs === []) {
        $this->say("FAIL $child: filho sem resposta 200 ou sem <loc>");
        $code = 1;
        continue;
      }
      foreach ($locs as $u) {
        $h = Verify::hostOf($u);
        if ($h !== null && !in_array($h, $allowed, true)) {
          $this->say("FAIL $u: host fora da política de produção");
          $code = 1;
        }
        $contentHosts[$h] = true;
      }
      $this->say(sprintf('PASS %s: %d URL(s) de conteúdo', $child, count($locs)));
    }
    foreach (array_keys($contentHosts) as $h) {
      // No servidor de testes, o host de conteúdo é verificado pelo equivalente de teste.
      $target = $env === 'test' ? Verify::testEquivalent((string) $h) : (string) $h;
      $probe = Verify::fetchWithHeaders("https://$target/");
      $ok = $probe !== null && (Verify::statusCode($probe['headers']) ?? 500) < 400;
      $this->say(sprintf('%s host de conteúdo %s (%s) responde %s', $ok ? 'PASS' : 'FAIL', $h, $target, $probe === null ? 'sem resposta' : (string) Verify::statusCode($probe['headers'])));
      $code = $ok ? $code : 1;
    }
    $this->say("sitemap ($env): " . ($code === 0 ? 'PASS' : 'FAIL'));
    return $code;
  }

  /**
   * Ambiente ativo, lido do arquivo neutro var/deployer/environment.json. Retorna NULL se ainda não foi definido.
   *
   * @return array{environment: string, site: string, changed_at: string}|null
   */
  private function currentEnvironment(): ?array {
    $file = $this->repoRoot . '/var/deployer/environment.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    return is_array($data) && ($data['schema'] ?? null) === 1 && isset($data['environment'], $data['site'], $data['changed_at'])
      ? ['environment' => (string) $data['environment'], 'site' => (string) $data['site'], 'changed_at' => (string) $data['changed_at']]
      : null;
  }

  /**
   * Ambiente de trabalho de uma função: --env explícito; senão o ambiente definido em
   * var/deployer/environment.json; senão "test" (estado seguro). Valor inválido vira "test"
   * e a função informa o ambiente usado.
   *
   * @param array<string, string|bool> $o
   */
  private function activeEnvironment(array $o): string {
    $sites = $this->json($this->toolRoot . '/config/deploy.json')['environments'] ?? [];
    if (is_string($o['env'] ?? null) && isset($sites[$o['env']])) {
      return (string) $o['env'];
    }
    $current = $this->currentEnvironment();
    return $current !== null && isset($sites[$current['environment']]) ? $current['environment'] : 'test';
  }

  /**
   * Ambiente do site: "environment" mostra o atual; "environment set --to=production|test" grava o
   * ambiente e o endereço do site em var/deployer/environment.json. O Portal lê esse arquivo para
   * escolher o conjunto de chaves (o deployer determina qual conjunto usar).
   *
   * @param array<string, string|bool> $o
   */
  private function environment(string $sub, array $o): int {
    if ($sub === '' || $sub === 'show') {
      $current = $this->currentEnvironment();
      $this->say($current === null
        ? 'environment: não definido (o Portal usa a configuração local)'
        : sprintf('environment: %s — %s (definido em %s)', $current['environment'], $current['site'], $current['changed_at']));
      return 0;
    }
    if ($sub !== 'set') {
      $this->err('uso: environment [show] | environment set --to=production|test');
      return 1;
    }
    $to = is_string($o['to'] ?? null) ? $o['to'] : '';
    $sites = $this->json($this->toolRoot . '/config/deploy.json')['environments'] ?? [];
    if (!isset($sites[$to])) {
      $this->err('environment: ambiente desconhecido: ' . $to);
      return 1;
    }
    $dir = $this->repoRoot . '/var/deployer';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
      $this->err('environment: não foi possível criar var/deployer');
      return 1;
    }
    $data = ['schema' => 1, 'environment' => $to, 'site' => (string) $sites[$to]['site'], 'changed_at' => gmdate('c')];
    $target = $dir . '/environment.json';
    $temp = $dir . '/.environment-' . bin2hex(random_bytes(6)) . '.tmp';
    $fh = @fopen($temp, 'xb');
    if ($fh === false) {
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
    // O painel lê o relatório: atualiza-o para refletir o ambiente recém-definido.
    return $this->report([]);
  }

  /** Grava o resultado da verificação de robots em var/deployer/indexing.json (só URLs públicas e estados). */
  private function writeIndexing(string $env, array $checks, bool $ok): void {
    $dir = $this->repoRoot . '/var/deployer';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
      $this->err('robots: não foi possível criar a pasta de destino do resultado de indexação');
      return;
    }
    $data = Indexing::build($env, $checks, $ok, gmdate('c'));
    file_put_contents($dir . '/' . Indexing::FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
  }

  /** @return mixed */
  private function readJsonFile(string $path): mixed {
    return is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
  }

  /**
   * Relatório neutro para o painel do ACULTA Portal (var/deployer/status.json). Contém só nomes,
   * contagens e estados: nunca valores de credenciais. Não faz rede; sitemap e robots são comandos
   * próprios e não entram no relatório.
   *
   * @param array<string, string|bool> $o
   */
  private function report(array $o): int {
    $out = is_string($o['out'] ?? null) ? $o['out'] : $this->repoRoot . '/var/deployer/status.json';
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
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
      $this->err('report: não foi possível criar a pasta de destino');
      return 1;
    }
    $temp = $dir . DIRECTORY_SEPARATOR . '.status-' . bin2hex(random_bytes(6)) . '.tmp';
    $fh = @fopen($temp, 'xb');
    if ($fh === false) {
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

  /** Fase 5: GET somente leitura nas entradas com probe e expect. */
  /** @param array<string, string|bool> $o */
  private function verify(array $o): int {
    $env = $this->activeEnvironment($o);
    $code = 0;
    $checked = 0;
    foreach ($this->registry()->entries() as $e) {
      if (!isset($e['probe'], $e['expect'])) {
        continue;
      }
      // Entrada já resolvida pelo responsável: no teste, a conferência é obsoleta e sai do verify.
      // A sonda de produção segue para a verificação pós-deploy (campo decision do registro).
      if ($env === 'test' && ($e['status'] ?? '') === 'resolved') {
        $this->say("SKIP {$e['id']}: resolvida; sonda de produção é verificação pós-deploy");
        continue;
      }
      $checked++;
      // Sondas e valores esperados são de produção; no teste viram os equivalentes de teste.
      $probe = $env === 'test' ? Verify::toTestEnvironment((string) $e['probe']) : (string) $e['probe'];
      $expect = $env === 'test' ? Verify::toTestEnvironment((string) $e['expect']) : (string) $e['expect'];
      $body = Verify::fetch($probe);
      if ($body === null) {
        $this->say("FAIL {$e['id']}: não foi possível ler {$probe} (URL inválida ou sem resposta)");
        $code = 1;
        continue;
      }
      if (Verify::evaluate($body, $expect)) {
        $this->say("PASS {$e['id']}: {$probe} contém o valor esperado ({$env})");
      } else {
        $this->say("FAIL {$e['id']}: {$probe} não contém o valor esperado ({$env})");
        $code = 1;
      }
    }
    $this->say($checked === 0 ? 'verify: nenhuma entrada com probe' : ($code === 0 ? 'verify: PASS' : 'verify: FAIL'));
    return $code;
  }

  /** @param array<string, string|bool> $o */
  private function register(array $o): int {
    if (isset($o['from-json'])) {
      return $this->importFindings((string) $o['from-json']);
    }
    $required = ['kind', 'page', 'current', 'expected', 'reason', 'owner'];
    foreach ($required as $k) {
      if (!isset($o[$k]) || $o[$k] === true || trim((string) $o[$k]) === '') {
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
    } catch (\InvalidArgumentException $e) {
      $this->err('register: ' . $e->getMessage());
      return 1;
    }
    $this->say("registrado: $id");
    return 0;
  }

  /** Fase 3: importa achados de um JSON (lista de objetos), tudo ou nada. */
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
      $findings = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
      $this->err('register: JSON de achados inválido');
      return 1;
    }
    if (!is_array($findings) || !array_is_list($findings)) {
      $this->err('register: o arquivo deve conter uma lista de achados');
      return 1;
    }
    try {
      $ids = $this->registry()->addMany($findings);
    } catch (\InvalidArgumentException $e) {
      $this->err('register: nenhum achado foi gravado: ' . $e->getMessage());
      return 1;
    }
    $this->say('importados: ' . implode(', ', $ids));
    return 0;
  }

  /** @param array<string, string|bool> $o */
  private function build(array $o): int {
    $target = is_string($o['target'] ?? null) ? $o['target'] : 'production';
    if (!in_array($target, ['production', 'test'], true)) {
      $this->err('build: --target aceita production ou test');
      return 1;
    }
    if (is_string($o['env'] ?? null) && $o['env'] !== $target) {
      $this->err("build: --env={$o['env']} diverge de --target=$target");
      return 1;
    }
    $out = $o['out'] ?? null;
    if (!is_string($out) || $out === '') {
      $this->err('build: --out=DIR é obrigatório');
      return 1;
    }
    $outAbs = $this->absolute($out);
    // Resolve symlinks do ancestral existente antes de comparar com o repositório.
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
    // Nunca sobrescreve: cada build vai para um diretório novo, preservando o anterior.
    if (file_exists($outAbs) && (!is_dir($outAbs) || count(scandir($outAbs)) > 2)) {
      $this->err('build: o diretório de saída já existe e não está vazio; use um diretório novo (builds anteriores são preservados)');
      return 1;
    }
    $blocking = $this->registry()->openBlocking();
    if ($target === 'production' && $blocking !== [] && !isset($o['allow-open-blocking'])) {
      $this->err('build: existem entradas bloqueantes abertas; resolva-as ou use --allow-open-blocking para um build de ensaio');
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
    if ($target === 'production' && Verify::isNoindex($robots['x_robots_tag'] ?? null)) {
      $this->err('build: a política de produção envia noindex; o build de produção não é gerado');
      return 1;
    }
    $report = ['tool' => 'aculta-deployer ' . self::VERSION, 'target' => $target, 'files' => [], 'dropped' => [], 'skipped_binary' => [], 'replacements' => 0, 'profile_rules' => 0];
    if (!is_dir($outAbs) && !mkdir($outAbs, 0775, true)) {
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
        } elseif ($target === 'production') {
          [$content, $count] = $transform->applyHosts($raw);
        } else {
          $content = $raw;
          $count = 0;
        }
        $profileRules = $transform->profileRules($target, $rel);
        if ($profileRules !== []) {
          $content = $transform->applyProfile($content, $profileRules, $rel);
          $report['profile_rules'] += count($profileRules);
        }
        $file = $outAbs . '/' . $rel;
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0775, true)) {
          throw new \RuntimeException("não foi possível criar diretório para $rel");
        }
        file_put_contents($file, $content);
        $report['files'][] = ['path' => $rel, 'replacements' => $count, 'sha256' => hash('sha256', $content)];
        $report['replacements'] += $count;
      }
      // Autoverificação: nenhum host de teste pode restar nos arquivos gerados de produção.
      foreach ($target === 'production' ? $report['files'] : [] as $f) {
        if (in_array($f['path'], $report['skipped_binary'], true)) {
          continue;
        }
        if ($transform->countTestHosts((string) file_get_contents($outAbs . '/' . $f['path'])) > 0) {
          throw new \RuntimeException('host de teste restou no arquivo gerado: ' . $f['path']);
        }
      }
    } catch (\RuntimeException $e) {
      $this->removeTree($outAbs);
      $this->err('build: ' . $e->getMessage() . ' (saída removida)');
      return 1;
    }
    file_put_contents($outAbs . '/deploy-policy.json', json_encode(['robots' => $robots], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    file_put_contents($outAbs . '/deploy-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $this->say(sprintf('build (%s): %d arquivos gravados, %d removidos, %d binários preservados, %d substituições de host, %d regras de perfil em %s',
      $target, count($report['files']), count($report['dropped']), count($report['skipped_binary']), $report['replacements'], $report['profile_rules'], $out));
    return 0;
  }

  /**
   * Plano de transição para um ambiente: gera o build do alvo, lista os arquivos de config/sync que mudam
   * em relação ao repositório e grava transition-plan.json com a importação parcial e o rollback.
   * Não altera nenhum Runtime.
   */
  private function transition(array $o): int {
    $target = is_string($o['target'] ?? null) ? $o['target'] : '';
    if (!in_array($target, ['production', 'test'], true)) {
      $this->err('transition: informe --target=production ou --target=test');
      return 1;
    }
    $out = $o['out'] ?? null;
    if (!is_string($out) || $out === '') {
      $this->err('transition: --out=DIR é obrigatório');
      return 1;
    }
    $buildOptions = ['target' => $target, 'out' => $out];
    if (isset($o['allow-open-blocking'])) {
      $buildOptions['allow-open-blocking'] = true;
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
    file_put_contents($outAbs . '/transition-plan.json', json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $this->say(sprintf('transition (%s): %d arquivo(s) de config/sync mudam; plano em %s/transition-plan.json', $target, count($changed), $out));
    foreach ($changed as $rel) {
      $this->say("  $rel");
    }
    return 0;
  }

  /** Prepara public_html para servir web/; por padrão, somente simula. */
  private function hostingerLayout(array $o): int {
    $domainRoot = $o['domain-root'] ?? null;
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
    } catch (\RuntimeException $e) {
      $this->err('hostinger-layout: ' . $e->getMessage());
      return 1;
    }
  }

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

  /** @return string[] */
  private function boundaryViolations(): array {
    $rules = $this->json($this->toolRoot . '/config/boundary.json');
    return (new Boundary($this->repoRoot, $this->toolRoot, $rules))->check();
  }

  /**
   * Arquivos do escopo com e-mail fixo dos domínios do projeto que não estão em environment_bound.
   *
   * @return array<string, string> caminho => primeiro endereço encontrado
   */
  private function unboundEnvironmentValues(): array {
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    $bound = array_map(static fn(array $e): string => (string) ($e['file'] ?? ''), $config['environment_bound'] ?? []);
    $found = [];
    foreach ($this->scopeFiles() as $rel) {
      if (in_array($rel, $bound, true)) {
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
   * Regras de perfil que não casam exatamente uma vez no config/sync atual (regra morta ou ambígua).
   *
   * @return string[]
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
          $transform->applyProfile((string) file_get_contents($file), [['pattern' => (string) $rule['pattern'], 'with' => (string) $rule['with']]], (string) $rule['file']);
        } catch (\RuntimeException $e) {
          $errors[] = "$target regra $i: " . $e->getMessage();
        }
      }
    }
    return $errors;
  }

  private function transform(): Transform {
    return new Transform($this->json($this->toolRoot . '/config/deploy.json'));
  }

  private function registry(): Registry {
    return new Registry($this->toolRoot . '/registry/deploy-registry.json');
  }

  /** @return string[] Caminhos relativos ao repositório, dentro do escopo. */
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

  /** @return array{scope: int, with_test_host: int, dropped: int, outside_scope: string[]} */
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

  /** @return array<string, mixed> */
  /** Confere, somente leitura, a tabela domain_transition contra os registros de domínio em config/sync. */
  /**
   * Confere (somente leitura) o cookie de sessão de cada ambiente contra os arquivos de serviços.
   */
  private function cookies(): int {
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    $environments = $config['session_cookie']['environments'] ?? null;
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
        // Produção: o arquivo real é provisionado no servidor; confere-se o modelo versionado.
        $check = $this->repoRoot . '/' . ($spec['source'] ?? '') . '.example';
        $label = 'modelo';
        if (!is_file($check)) {
          $this->err("cookies: $name sem arquivo nem modelo ({$spec['source']})");
          $code = 1;
          continue;
        }
      }
      $found = preg_match("/^\s*cookie_domain:\s*['\"]?([^'\"\s]+)['\"]?\s*$/m", (string) file_get_contents($check), $m) === 1 ? $m[1] : null;
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
      if (preg_match("/^\s*cookie_domain:\s*['\"]?([^'\"\s]+)/m", (string) file_get_contents($file), $m) === 1 && !in_array($m[1], $declared, true)) {
        $this->say('dívida ' . basename($file) . ": cookie_domain {$m[1]} não corresponde a nenhum ambiente declarado (ver docs/COOKIES-AMBIENTE.md)");
      }
    }
    return $code;
  }

  private function domains(): int {
    $config = $this->json($this->toolRoot . '/config/deploy.json');
    $table = $config['domain_transition']['entries'] ?? null;
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
      $current = is_file($record) && preg_match('/^hostname: (.+)$/m', (string) file_get_contents($record), $m) === 1 ? trim($m[1]) : null;
      if ($current === null) {
        $pending++;
        $this->say("pendente $id: sem registro de domínio em config/sync (destino $prod)");
      } elseif ($current === $prod) {
        $aligned++;
        $this->say("ok $id: config/sync = $prod ($rule)");
      } else {
        $pending++;
        $this->say("pendente $id: config/sync diz $current; destino de produção $prod ($rule); muda no deploy do RC");
      }
    }
    $this->say(sprintf('domains: %d alinhado(s), %d pendente(s) de RC; somente leitura', $aligned, $pending));
    return 0;
  }

  private function json(string $path): array {
    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
  }

  private function absolute(string $path): string {
    return str_starts_with($path, '/') ? rtrim($path, '/') : rtrim(getcwd() . '/' . $path, '/');
  }

  /** @param string[] $args @return array<string, string|bool> */
  private function options(array $args): array {
    $out = [];
    foreach ($args as $a) {
      if (str_starts_with($a, '--')) {
        [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, true);
        $out[$k] = $v;
      }
    }
    return $out;
  }

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

  private function say(string $line): int {
    fwrite(STDOUT, $line . "\n");
    return 0;
  }

  private function err(string $line): void {
    fwrite(STDERR, $line . "\n");
  }

}
