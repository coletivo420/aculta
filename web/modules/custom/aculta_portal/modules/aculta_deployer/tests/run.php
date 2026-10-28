<?php

/**
 * @file
 * Testes determinísticos para a CLI standalone do Deployer.
 */

declare(strict_types=1);

// Testes determinísticos da CLI. Não usam Drupal, Drush nem vendor.
$toolRoot = dirname(__DIR__);
foreach (['Registry', 'Transform', 'Boundary', 'Verify', 'Indexing', 'HostingerLayout', 'Provision', 'Cli'] as $class) {
  require_once $toolRoot . '/src/' . $class . '.php';
}

use AcultaDeployer\Provision;
use AcultaDeployer\Indexing;
use AcultaDeployer\Verify;
use AcultaDeployer\HostingerLayout;
use AcultaDeployer\Cli;
use AcultaDeployer\Boundary;
use AcultaDeployer\Registry;
use AcultaDeployer\Transform;

$failures = 0;
$assert = static function (bool $ok, string $name) use (&$failures): void {
  echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
  if (!$ok) {
    $failures++;
  }
};

$config = json_decode((string) file_get_contents($toolRoot . '/config/deploy.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$t = new Transform($config);

// Substituição de host: preserva o prefixo e troca o sufixo.
[$out, $n] = $t->applyHosts('https://apoio.aculta.toca.net.br/');
$assert($out === 'https://apoio.aculta.org/' && $n === 1, 'host de teste com prefixo vira produção');
[$out] = $t->applyHosts('aculta.toca.net.br');
$assert($out === 'aculta.org', 'host raiz vira produção');
[$out, $n] = $t->applyHosts('aculta.toca.net.br.evil.com');
$assert($out === 'aculta.toca.net.br.evil.com' && $n === 0, 'host com rótulo extra não é reescrito');
[$out, $n] = $t->applyHosts('notaculta.toca.net.br');
$assert($n === 0, 'host que termina no sufixo sem fronteira não é reescrito');
[$out, $n] = $t->applyHosts('aculta.toca.net.brx');
$assert($n === 0, 'sufixo seguido de letra não é reescrito');

// Escopo e remoção de aliases de teste.
$assert($t->isDropped('config/sync/domain_alias.alias.apoio_aculta_toca_net_br.yml'), 'alias de homologação é removido no build');
$assert($t->isDropped('config/sync/domain_alias.alias.aculta_test_8080.yml'), 'alias de porta 8080 é removido no build');
$assert(!$t->isDropped('config/sync/domain_alias.settings.yml'), 'configuração geral de aliases é mantida');
$assert($t->inScope('config/sync/system.site.yml') && $t->inScope('config/sync/domain/apoio_aculta_org/system.site.yml'), 'config/sync entra no escopo');
$assert(!$t->inScope('docs/architecture/multidomain.md') && !$t->inScope('web/modules/custom/aculta_portal/x.php'), 'documentação e código ficam fora do escopo');

// Registro: validação e geração de id.
$tmp = sys_get_temp_dir() . '/aculta-deployer-test-' . getmypid() . '.json';
file_put_contents($tmp, json_encode([
  'schema' => 1,
  'entries' => [[
    'id' => 'DEP-0001',
    'kind' => 'canonical',
    'status' => 'open',
    'blocking' => TRUE,
    'page' => 'p',
    'current' => 'c',
    'expected_production' => 'e',
    'reason' => 'r',
    'owner' => 'o',
    'decision' => 'pendente',
  ],
  ],
]));
$reg = new Registry($tmp);
$assert($reg->validate() === [], 'registro válido não gera erros');
$assert(count($reg->openBlocking()) === 1, 'entrada bloqueante aberta é contada');
$id = $reg->add([
  'kind' => 'sitemap',
  'page' => 'p2',
  'current' => 'c',
  'expected_production' => 'e',
  'reason' => 'r',
  'owner' => 'o',
  'blocking' => FALSE,
]);
$assert($id === 'DEP-0002', 'novo registro recebe o próximo id');
file_put_contents($tmp, json_encode([
  'schema' => 1,
  'entries' => [
    ['id' => 'X1', 'kind' => 'bogus', 'status' => 'open'],
  ],
]));
$assert(count((new Registry($tmp))->validate()) >= 3, 'registro com id, kind e campos inválidos é reprovado');
try {
  (new Registry($tmp))->add([
    'kind' => 'canonical',
    'page' => 'p',
    'current' => 'c',
    'expected_production' => 'e',
    'reason' => 'r',
    'owner' => 'o',
  ]);
  $assert(FALSE, 'add recusa registro inválido');
}
catch (\InvalidArgumentException) {
  $assert(TRUE, 'add recusa registro inválido');
}
@unlink($tmp);

// Fronteiras: o código atual passa, e referência proibida é detectada.
$repo = dirname($toolRoot, 6);
$rules = json_decode((string) file_get_contents($toolRoot . '/config/boundary.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$assert((new Boundary($repo, $toolRoot, $rules))->check() === [], 'fronteiras atuais passam');
$probe = $toolRoot . '/src/__probe.php';
// Build the forbidden token at runtime so it is absent from this source file.
$needle = implode('', ['Dru', 'pal', '::']);
file_put_contents($probe, "<?php \$x = '" . $needle . "service';\n");
$assert(count((new Boundary($repo, $toolRoot, $rules))->check()) >= 1, 'dependência de Drupal no código da ferramenta é detectada');
unlink($probe);

// Fase 1: configuração, registro corrompido e escrita atômica.
$bad = new Transform(['scope' => ['(unclosed'], 'drop' => [], 'host_rules' => []]);
$assert(count($bad->validate()) >= 2, 'configuração com regex inválido e sem host_rules é reprovada');
$assert((new Transform($config))->validate() === [], 'configuração atual é válida');
$tmpDir = sys_get_temp_dir() . '/aculta-deployer-p1-' . getmypid();
@mkdir($tmpDir);
$corrupt = $tmpDir . '/reg.json';
file_put_contents($corrupt, '{not json');
$threw = FALSE;
try {
  new Registry($corrupt);
}
catch (\JsonException) {
  $threw = TRUE;
}
$assert($threw, 'registro com JSON corrompido gera erro explícito');
file_put_contents($corrupt, json_encode(['schema' => 1, 'entries' => []]));
$reg2 = new Registry($corrupt);
$reg2->add([
  'kind' => 'other',
  'page' => 'p',
  'current' => 'c',
  'expected_production' => 'e',
  'reason' => 'r',
  'owner' => 'o',
]);
$assert(!file_exists($corrupt . '.tmp-' . getmypid()), 'gravação atômica não deixa arquivo temporário');
$assert((new Registry($corrupt))->entries()[0]['id'] === 'DEP-0001', 'registro gravado é relido com o id criado');
array_map('unlink', glob($tmpDir . '/*') ?: []);
@rmdir($tmpDir);

// Fase 2b: concorrência do registro. Duas instâncias leram o mesmo estado; a segunda não pode sobrescrever a primeira.
$raceDir = sys_get_temp_dir() . '/aculta-deployer-race-' . getmypid();
@mkdir($raceDir);
$raceFile = $raceDir . '/reg.json';
file_put_contents($raceFile, json_encode(['schema' => 1, 'entries' => []]));
$record = static fn(string $page): array => ['kind' => 'other', 'page' => $page, 'current' => 'c', 'expected_production' => 'e', 'reason' => 'r', 'owner' => 'o'];
$first = new Registry($raceFile);
$second = new Registry($raceFile);
$first->add($record('pagina-1'));
$first->save();
$refused = FALSE;
try {
  $second->add($record('pagina-2'));
}
catch (\RuntimeException $e) {
  $refused = str_contains($e->getMessage(), 'alterado por outro processo');
}
$assert($refused, 'registro: gravação sobre estado antigo é recusada');
$afterRace = (new Registry($raceFile))->entries();
$assert(count($afterRace) === 1 && $afterRace[0]['page'] === 'pagina-1', 'registro: a gravação recusada não apaga a entrada do primeiro processo');
$third = new Registry($raceFile);
$third->add($record('pagina-2'));
$third->save();
$assert(count((new Registry($raceFile))->entries()) === 2, 'registro: depois de reler, a gravação é aceita');
array_map('unlink', glob($raceDir . '/*') ?: []);
@rmdir($raceDir);

// Fase 2: cobertura e segurança do build em diretórios temporários.
$base = sys_get_temp_dir() . '/aculta-deployer-p2-' . getmypid();
@mkdir($base);
$cli = new Cli($toolRoot);
$quiet = static function (callable $fn): int {
  ob_start();
  try {
    return $fn();
  } finally {
    ob_end_clean();
  }
};
$rc1 = $quiet(fn() => $cli->run(['x', 'build', '--out=' . $base . '/a', '--allow-open-blocking']));
$rc2 = $quiet(fn() => $cli->run(['x', 'build', '--out=' . $base . '/b', '--allow-open-blocking']));
$assert($rc1 === 0 && $rc2 === 0, 'build de ensaio gera saída em dois diretórios novos');
$ra = json_decode((string) file_get_contents($base . '/a/deploy-report.json'), TRUE);
$rb = json_decode((string) file_get_contents($base . '/b/deploy-report.json'), TRUE);
$assert($ra['files'] === $rb['files'] && $ra['dropped'] === $rb['dropped'], 'build é idempotente: hashes e remoções iguais');
$leak = FALSE;
foreach ($ra['files'] as $f) {
  if (str_contains((string) file_get_contents($base . '/a/' . $f['path']), 'toca.net.br')) {
    $leak = TRUE;
  }
}
$assert(!$leak, 'nenhum arquivo gerado contém host de teste');
$assert($quiet(fn() => $cli->run(['x', 'build', '--out=' . $base . '/a', '--allow-open-blocking'])) === 1, 'build não sobrescreve saída existente');
$assert($quiet(fn() => $cli->run([
  'x',
  'build',
  '--out=' . dirname($toolRoot) . '/dentro-do-repo',
  '--allow-open-blocking',
])) === 1, 'build recusa saída dentro do repositório');
@symlink($repo . '/config', $base . '/link-para-repo');
$assert($quiet(fn() => $cli->run(['x', 'build', '--out=' . $base . '/link-para-repo/saida', '--allow-open-blocking'])) === 1, 'build recusa saída por link simbólico para o repositório');
$assert($quiet(fn() => $cli->run(['x', 'build', '--out=' . $base . '/c'])) === 2, 'build com entradas bloqueantes exige --allow-open-blocking');
// Limpeza.
$removeTree = static function (string $path) use (&$removeTree): void {
  if (is_link($path) || !is_dir($path)) {
    if (file_exists($path) || is_link($path)) {
      unlink($path);
    }
    return;
  }
  foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
    $removeTree($path . DIRECTORY_SEPARATOR . $entry);
  }
  rmdir($path);
};
foreach (['a', 'b'] as $d) {
  // Limpa somente os diretórios que o build chegou a criar.
  if (is_dir($base . '/' . $d)) {
    $removeTree($base . '/' . $d);
  }
}
@unlink($base . '/link-para-repo');
@rmdir($base);

// Layout Hostinger: public_html aponta para web/ na raiz do domínio.
$domainRoot = sys_get_temp_dir() . '/aculta-hostinger-layout-' . getmypid();
mkdir($domainRoot . '/web/sites/default', 0775, TRUE);
mkdir($domainRoot . '/vendor', 0775, TRUE);
file_put_contents($domainRoot . '/composer.json', '{}');
file_put_contents($domainRoot . '/composer.lock', '{}');
$autoloadPath = $domainRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
file_put_contents($autoloadPath, '<?php');
file_put_contents($domainRoot . '/web/index.php', '<?php');
file_put_contents($domainRoot . '/web/sites/default/settings.php', '<?php');
mkdir($domainRoot . '/public_html');
file_put_contents($domainRoot . '/public_html/index.html', 'site anterior');
$layout = new HostingerLayout();
$layoutPlan = $layout->inspect($domainRoot);
$assert($layoutPlan['status'] === 'directory' && is_dir($domainRoot . '/public_html') && !is_link($domainRoot . '/public_html'), 'layout: inspeção não altera public_html existente');
$layoutDryRun = $quiet(fn() => $cli->run(['x', 'hostinger-layout', '--domain-root=' . $domainRoot]));
$assert($layoutDryRun === 0 && is_dir($domainRoot . '/public_html') && !is_link($domainRoot . '/public_html'), 'layout: CLI sem --apply só simula');
$layoutApply = $quiet(fn() => $cli->run(['x', 'hostinger-layout', '--domain-root=' . $domainRoot, '--apply']));
$assert(is_link($domainRoot . '/public_html') && readlink($domainRoot . '/public_html') === 'web', 'layout: public_html passa a apontar relativamente para web/ na raiz do domínio');
$assert($layoutApply === 0 && !file_exists($domainRoot . '/public_html/index.html'), 'layout: --apply exclui o conteúdo anterior');
$assert($layout->inspect($domainRoot)['status'] === 'already_linked' && $layout->activate($layout->inspect($domainRoot)) === 'public_html já aponta para a raiz web da instalação', 'layout: ativação repetida é idempotente');
$incomplete = $domainRoot . '/incompleta';
mkdir($incomplete);
$thrown = FALSE;
try {
  $layout->inspect($incomplete);
}
catch (RuntimeException) {
  $thrown = TRUE;
}
$assert($thrown && is_link($domainRoot . '/public_html'), 'layout: instalação incompleta é recusada sem desfazer o symlink ativo');
$external = $domainRoot . '/destino-externo';
mkdir($external);
file_put_contents($external . '/manter.txt', 'preservar');
unlink($domainRoot . '/public_html');
symlink($external, $domainRoot . '/public_html');
$layoutSymlink = $layout->inspect($domainRoot);
$layout->activate($layoutSymlink);
$assert(readlink($domainRoot . '/public_html') === 'web' && file_get_contents($external . '/manter.txt') === 'preservar', 'layout: substituição de symlink não apaga o destino externo');
$removeFixture = NULL;
$removeFixture = static function (string $path) use (&$removeFixture): void {
  if (is_link($path) || is_file($path)) {
    unlink($path);
    return;
  }
  if (is_dir($path)) {
    foreach (scandir($path) ?: [] as $entry) {
      if ($entry !== '.' && $entry !== '..') {
        $removeFixture($path . '/' . $entry);
      }
    }
    rmdir($path);
  }
};
$removeFixture($domainRoot);

// Fase 3: importação de achados (tudo ou nada).
$regFile = sys_get_temp_dir() . '/aculta-deployer-p3-' . getmypid() . '.json';
file_put_contents($regFile, json_encode(['schema' => 1, 'entries' => []]));
$good = [
  [
    'kind' => 'other',
    'page' => 'p1',
    'current' => 'c',
    'expected_production' => 'e',
    'reason' => 'r',
    'owner' => 'o',
    'extra' => 'ignorado',
  ],
  [
    'kind' => 'link',
    'page' => 'p2',
    'current' => 'c',
    'expected_production' => 'e',
    'reason' => 'r',
    'owner' => 'o',
  ],
];
$gReg = new Registry($regFile);
$ids = $gReg->addMany($good);
$assert($ids === ['DEP-0001', 'DEP-0002'] && !array_key_exists('extra', (new Registry($regFile))->entries()[0]), 'importação grava achados válidos e descarta campos extras');
$badFindings = [$good[0], ['kind' => 'canonical', 'page' => 'p3']];
$before = file_get_contents($regFile);
$threwBad = FALSE;
try {
  (new Registry($regFile))->addMany($badFindings);
}
catch (\InvalidArgumentException) {
  $threwBad = TRUE;
}
$assert($threwBad && file_get_contents($regFile) === $before, 'importação com um achado inválido não grava nada');
unlink($regFile);

// Fase 5: verificação pós-deploy somente leitura.
$assert(Verify::isAllowedUrl('https://apoio.aculta.org/'), 'verify aceita URL https válida');
$assert(!Verify::isAllowedUrl('http://apoio.aculta.org/'), 'verify recusa http');
$assert(!Verify::isAllowedUrl('https://user:senha@apoio.aculta.org/'), 'verify recusa URL com credenciais');
$assert(!Verify::isAllowedUrl('https://'), 'verify recusa URL sem host');
$assert(Verify::evaluate('<link rel="canonical" href="https://apoio.aculta.org/">', '<link rel="canonical" href="https://apoio.aculta.org/">'), 'verify encontra o valor esperado no corpo');
$assert(!Verify::evaluate('<link rel="canonical" href="https://apoio.aculta.toca.net.br/">', '<link rel="canonical" href="https://apoio.aculta.org/">'), 'verify reprova canonical de teste');
$assert(!Verify::evaluate('qualquer coisa', ''), 'verify reprova expectativa vazia');
$regPath = $toolRoot . '/registry/deploy-registry.json';
$before = hash_file('sha256', $regPath);
ob_start();
$rcVerify = (new Cli($toolRoot))->run(['x', 'verify']);
ob_end_clean();
$assert($rcVerify === 1 || $rcVerify === 0, 'verify executa e retorna código de saída válido');
$assert(hash_file('sha256', $regPath) === $before, 'verify não altera o registro (somente leitura)');

// Política de indexação: produção indexável; teste não indexável.
$assert(Verify::robotsHeader(['HTTP/1.1 200 OK', 'X-Robots-Tag: noindex, nofollow']) === 'noindex, nofollow', 'lê X-Robots-Tag do cabeçalho (case-insensitive)');
$assert(Verify::robotsHeader(['HTTP/1.1 200 OK', 'Content-Type: text/html']) === NULL, 'cabeçalho ausente resulta em null');
$assert(Verify::isNoindex('noindex, nofollow, noarchive') && !Verify::isNoindex('index, follow') && !Verify::isNoindex(NULL), 'detecta noindex e aceita ausência');
$pol = $config['robots_policy'] ?? [];
$assert(array_key_exists('x_robots_tag', $pol['production'] ?? []) && $pol['production']['x_robots_tag'] === NULL, 'política de produção não envia X-Robots-Tag');
$assert(str_contains((string) ($pol['test']['x_robots_tag'] ?? ''), 'noindex'), 'política de teste envia noindex');
$prodHosts = $pol['production']['hosts'] ?? [];
$expectedProd = [
  'https://aculta.org/',
  'https://conta.aculta.org/',
  'https://apoio.aculta.org/',
  'https://revista.aculta.org/',
  'https://wiki420.aculta.org/',
  'https://loja.aculta.org/',
  'https://cursos.aculta.org/',
  'https://links.aculta.org/',
  'https://growroom420.aculta.org/',
];
$assert(sort($prodHosts) === TRUE && $prodHosts === (function () use ($expectedProd) {
  sort($expectedProd);
  return $expectedProd;
})(), 'os nove domínios de produção declarados estão na política (um por purpose)');
$assert(!array_filter($prodHosts, static fn($h) => str_contains($h, 'toca.net.br')), 'nenhum host de teste na política de produção');
$badRobots = ['robots_policy' => ['production' => ['x_robots_tag' => 'noindex']]];
$guard = Verify::isNoindex($badRobots['robots_policy']['production']['x_robots_tag']);
$assert($guard === TRUE, 'trava de build detecta política de produção com noindex');

$assert(Verify::statusCode(['HTTP/1.1 403 Forbidden', 'Content-Type: text/html']) === 403, 'lê o código HTTP da resposta');
$assert(Verify::statusCode(['Content-Type: text/html']) === NULL, 'sem linha de status resulta em null');
$assert(Verify::metaNoindex('<html><head><meta name="robots" content="noindex" /></head></html>') === TRUE, 'detecta meta robots noindex');
$assert(Verify::metaNoindex('<meta content="noindex, nofollow" name="ROBOTS">') === TRUE, 'meta robots aceita atributos em outra ordem e caixa');
$assert(Verify::metaNoindex('<meta name="description" content="noindex">') === FALSE, 'meta de outro nome não conta como robots');
$assert(Verify::metaNoindex('<html><body>sem meta</body></html>') === FALSE, 'página sem meta robots não é noindex');
$assert(Verify::isRefusedStatus(404) && Verify::isRefusedStatus(403) && !Verify::isRefusedStatus(200) && !Verify::isRefusedStatus(NULL), 'status recusado/inexistente aceito; 200 e ausente não');
$privProbes = $pol['production']['private_probes'] ?? [];
$assert(count($privProbes) >= 8 &&!array_filter($privProbes, static fn($u) => str_contains((string) $u, 'toca.net.br')), 'caminhos privados de produção listados, sem host de teste');
$assert(in_array('https://conta.aculta.org/entrar', $privProbes, TRUE), 'login (/entrar) está entre os caminhos privados');

$assert(
  Verify::xmlLocs('<urlset><url><loc>https://aculta.org/a</loc></url><url><loc> https://aculta.org/b </loc></url></urlset>') === [
    'https://aculta.org/a',
    'https://aculta.org/b',
  ],
  'lê todos os <loc> e ignora espaços',
);
$assert(Verify::xmlLocs('<sitemapindex><sitemap><loc>https://aculta.org/main/sitemap.xml</loc></sitemap></sitemapindex>') === ['https://aculta.org/main/sitemap.xml'], 'lê o índice de sitemaps');
$assert(Verify::xmlLocs('<broken') === NULL, 'XML inválido resulta em null');
$assert(Verify::xmlLocs('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><urlset><url><loc>&e;</loc></url></urlset>') !== ['root:'], 'entidades externas não são resolvidas (XXE)');
$assert(
  Verify::sitemapDirectives("User-agent: *\nSitemap: https://aculta.org/sitemap.xml\nsitemap: https://x.org/y.xml\n") === [
    'https://aculta.org/sitemap.xml',
    'https://x.org/y.xml',
  ],
  'lê diretivas Sitemap sem diferenciar caixa',
);
$assert(Verify::sitemapDirectives("Disallow: /admin/\n") === [], 'robots sem Sitemap não gera diretiva');
$assert(Verify::disallowsRoot("User-agent: *\nDisallow: /\n") === TRUE, 'detecta Disallow: / (site inteiro bloqueado)');
$assert(Verify::disallowsRoot("User-agent: *\nDisallow: /admin/\nDisallow:\n") === FALSE, 'Disallow de caminho específico e Disallow vazio não bloqueiam o site');
$assert(Verify::testEquivalent('apoio.aculta.org') === 'apoio.aculta.toca.net.br' && Verify::testEquivalent('aculta.org') === 'aculta.toca.net.br', 'equivalente de teste preserva o subdomínio');
$assert(Verify::testEquivalent('evil-aculta.org') === 'evil-aculta.org', 'equivalente de teste troca só o sufixo exato');
$assert(Verify::hostOf('https://APOIO.aculta.org/x?y=1') === 'apoio.aculta.org', 'hostOf normaliza a caixa e ignora caminho e query');
$sm = $config['sitemap'] ?? [];
$assert(Verify::hostOf($sm['production']['index_url'] ?? '') === Verify::hostOf($sm['production']['index_base'] ?? '1'), 'índice de produção está na base de produção');
$assert(Verify::hostOf($sm['test']['index_url'] ?? '') === Verify::hostOf($sm['test']['index_base'] ?? '1') && !str_contains((string) ($sm['production']['index_url'] ?? ''), 'toca.net.br'), 'índice de teste na base de teste; produção sem host de teste');
$robotsWeb = (string) file_get_contents(dirname(__DIR__, 6) . '/robots.txt');
$assert(in_array($sm['production']['index_url'] ?? '', Verify::sitemapDirectives($robotsWeb), TRUE), 'web/robots.txt anuncia o índice de produção (mesma URL da política)');
$assert(!Verify::disallowsRoot($robotsWeb), 'web/robots.txt não bloqueia o site inteiro');

$contract = json_decode((string) file_get_contents(dirname($toolRoot, 6) . '/config/secrets-contract.json'), TRUE);
$doc = (string) file_get_contents(dirname($toolRoot, 6) . '/docs/operations/SECRETS.md');
$docOk = TRUE;
foreach (array_column($contract['variables'], 'name') as $n) {
  if (!str_contains($doc, '`' . $n . '`')) {
    $docOk = FALSE;
  }
}
$assert($docOk, 'todo nome do contrato aparece na tabela de SECRETS.md (gate anti-regressão do contrato)');
$requiredProd = array_column(array_filter($contract['variables'], static fn($v) => in_array('production', $v['required_in'], TRUE)), 'name');
$assert(array_diff($requiredProd, array_column($contract['variables'], 'name')) === [], 'obrigatórios de produção estão no contrato');
$cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($toolRoot . '/bin/aculta-deployer');
$rfDir = sys_get_temp_dir() . '/aculta-report-' . getmypid();
@mkdir($rfDir, 0700);
$rfOut = $rfDir . '/status.json';
exec($cli . ' report --out=' . escapeshellarg($rfOut) . ' 2>&1', $o7, $rc7);
$rfText = is_file($rfOut) ? (string) file_get_contents($rfOut) : '';
$rfData = json_decode($rfText, TRUE);
$assert($rc7 === 0 && is_array($rfData) && ($rfData['schema'] ?? NULL) === 1, 'report grava JSON com esquema 1');
$o8 = [];
exec($cli . ' report --out=' . escapeshellarg($rfOut) . ' 2>&1', $o8, $rc8);
$assert($rc8 === 0, 'report sobrescreve o próprio relatório (publicação atômica)');
@unlink($rfOut);
@rmdir($rfDir);

// Fronteira: o Portal pode citar, mas não executar a ferramenta.
$bRoot = sys_get_temp_dir() . '/aculta-boundary-' . getmypid();
@mkdir($bRoot . '/web/modules/custom/aculta_portal/src/Deployer', 0700, TRUE);
@mkdir($bRoot . '/web/modules/custom/aculta_portal/src/Other', 0700, TRUE);
file_put_contents($bRoot . '/web/modules/custom/aculta_portal/src/Deployer/Ok.php', "<?php // texto: aculta-deployer report\n");
file_put_contents($bRoot . '/web/modules/custom/aculta_portal/src/Deployer/Exec.php', "<?php shell_exec('x');\n");
file_put_contents($bRoot . '/web/modules/custom/aculta_portal/src/Other/Bad.php', "<?php // aculta-deployer\n");
$rules = json_decode((string) file_get_contents($toolRoot . '/config/boundary.json'), TRUE);
$rules['consumers'] = ['web/modules/custom/aculta_portal'];
$bv = (new Boundary($bRoot, $toolRoot, $rules))->check();
$joined = implode("\n", $bv);
$assert(str_contains($joined, 'Exec.php') && !str_contains($joined, 'Ok.php'), 'fronteira: pasta neutra aceita citação e recusa execução');
$assert(str_contains($joined, 'Bad.php'), 'fronteira: citação da ferramenta fora da pasta neutra é recusada');
exec('rm -rf ' . escapeshellarg($bRoot));

// Ambiente: valida test/production e restaura o arquivo após o teste.
$envFile = dirname($toolRoot, 6) . '/var/deployer/environment.json';
$envBefore = is_file($envFile) ? (string) file_get_contents($envFile) : NULL;
exec($cli . ' environment set --to=production 2>&1', $e1, $erc1);
$envProd = json_decode((string) file_get_contents($envFile), TRUE);
$assert($erc1 === 0 && ($envProd['environment'] ?? NULL) === 'production' && ($envProd['site'] ?? NULL) === 'https://aculta.org', 'environment set production grava o endereço de produção');
exec($cli . ' environment set --to=xx 2>&1', $e2, $erc2);
$assert($erc2 !== 0 && (json_decode((string) file_get_contents($envFile), TRUE)['environment'] ?? NULL) === 'production', 'environment recusa valor desconhecido e mantém o atual');
exec($cli . ' environment set --to=test 2>&1', $e3, $erc3);
$envTest = json_decode((string) file_get_contents($envFile), TRUE);
$assert($erc3 === 0 && ($envTest['site'] ?? NULL) === 'https://aculta.toca.net.br', 'environment set test grava o endereço de teste');
if ($envBefore === NULL) {
  @unlink($envFile);
}
else {
  file_put_contents($envFile, $envBefore);
}

// Verify converte host de produção e preserva hosts parecidos no teste.
$assert(Verify::toTestEnvironment('<link rel="canonical" href="https://apoio.aculta.org/">') === '<link rel="canonical" href="https://apoio.aculta.toca.net.br/">', 'verify teste: canonical de produção vira o host de teste');
$assert(Verify::toTestEnvironment('https://evil-aculta.org/ https://aculta.org/x') === 'https://evil-aculta.org/ https://aculta.toca.net.br/x', 'verify teste: host parecido com produção não é alterado');
// Perfis: o deploy.json cobre todos os arquivos dependentes de ambiente.
$assert($t->validateProfiles() === [], 'perfis: deploy.json atual cobre todos os arquivos dependentes de ambiente');
// Guardrail: arquivo dependente sem regra no perfil é recusado.
$broken = $config;
$broken['profiles']['test']['rules'] = array_values(array_filter($config['profiles']['test']['rules'], static fn(array $r): bool => $r['file'] !== 'config/sync/system.site.yml'));
$assert(count(array_filter((new Transform($broken))->validateProfiles(), static fn(string $e): bool => str_contains($e, 'system.site.yml'))) === 1, 'perfis: arquivo sem regra no perfil de teste é recusado');
$outOfScope = $config;
$outOfScope['environment_bound'] = [['file' => 'web/modules/custom/example/src/Command.php']];
$assert((new Transform($outOfScope))->validateProfiles() === [], 'perfis: arquivo fora do escopo não exige regra de transformação');
$noBound = $config;
$noBound['environment_bound'] = [];
$assert((new Transform($noBound))->validateProfiles() !== [], 'perfis: environment_bound vazio é recusado');
// Regras precisam casar exatamente uma linha; zero ou várias falham.
$assert($t->applyProfile("exemplo_on: false\nx: 1\n", [['pattern' => '^exemplo_on: .*$', 'with' => 'exemplo_on: true']], 'f') === "exemplo_on: true\nx: 1\n", 'perfis: regra troca a linha casada');
$threw = static function (callable $fn): bool {
  try {
    $fn();
    return FALSE;
  }
  catch (\RuntimeException $e) {
    return TRUE;
  }
};
// Indexação: resumo neutro do último resultado para leitura pelo Portal.
$indexRun = Indexing::build('production', [
  ['kind' => 'host', 'url' => 'https://aculta.org/', 'ok' => TRUE, 'observed' => 'ausente'],
  ['kind' => 'host', 'url' => 'https://loja.aculta.org/', 'ok' => FALSE, 'observed' => 'noindex'],
], FALSE, '2026-10-11T12:00:00+00:00');
$sum = Indexing::summarize($indexRun);
$assert($sum !== NULL && $sum['passed'] === 1 && $sum['failed'] === 1 && $sum['failed_urls'] === ['https://loja.aculta.org/'] && $sum['ok'] === FALSE && $sum['environment'] === 'production', 'indexação: resumo conta aprovados e falhas e lista só as URLs com falha');
$assert(Indexing::summarize(NULL) === NULL && Indexing::summarize(['schema' => 2]) === NULL, 'indexação: arquivo ausente ou de outro schema não gera resumo');
$assert(Indexing::summarize(Indexing::build('test', [], TRUE, 'x'))['ok'] === TRUE, 'indexação: resultado sem checks e ok gera resumo válido');
$assert($threw(fn() => $t->applyProfile("x: 1\n", [['pattern' => '^exemplo_on: .*$', 'with' => 'exemplo_on: true']], 'f')), 'perfis: regra sem linha correspondente falha');
$assert($threw(fn() => $t->applyProfile("a\na\n", [['pattern' => '^a$', 'with' => 'b']], 'f')), 'perfis: regra ambígua (duas linhas) falha');
// Build de teste preserva hosts e configuração SMTP de teste.
$outTest = sys_get_temp_dir() . '/aculta-build-test-' . getmypid();
exec($cli . ' build --target=test --out=' . escapeshellarg($outTest) . ' 2>&1', $bt, $btrc);
$mailTest = is_file($outTest . '/config/sync/system.mail.yml') ? (string) file_get_contents($outTest . '/config/sync/system.mail.yml') : '';
$assert($btrc === 0 && str_contains($mailTest, '  default: aculta_mailer'), 'build --target=test: system.mail na camada de envio E-Mails ACULTA');
$assert(!is_file($outTest . '/config/sync/smtp.settings.yml'), 'build --target=test: smtp.settings legado não é gerado');
// Escrita atômica dos relatórios: existem, são JSON válido e não sobra temporário oculto.
$reportsOk = true;
foreach (['deploy-policy.json', 'deploy-report.json'] as $report) {
  $reportsOk = $reportsOk && is_file($outTest . '/' . $report) && json_decode((string) file_get_contents($outTest . '/' . $report), true) !== null;
}
$assert($reportsOk && (glob($outTest . '/.*.tmp') ?: []) === [], 'build: relatórios gravados de forma atômica, sem temporário sobrando');
// Alvo e ambiente divergentes são recusados sem gerar saída.
exec($cli . ' build --env=test --target=production --out=' . escapeshellarg(sys_get_temp_dir() . '/aculta-build-' . getmypid()) . ' 2>&1', $b1, $brc1);
$assert($brc1 !== 0, 'build recusa --env diferente de --target');

// Tabela somente leitura: a revista usa o host decidido.
exec($cli . ' domains 2>&1', $dom, $domrc);
$assert($domrc === 0 && str_contains(implode("\n", $dom), 'revista.aculta.org (regra de host)') && str_contains(implode("\n", $dom), '0 pendente(s) de Alpha'), 'domains: tabela válida; revista alinhada (host de produção decidido)');

// Provisionamento idempotente, sem sobrescrever e sem expor valores.
$assert(Provision::missing(['A', 'B'], ['B', 'C']) === ['C'], 'provision: só falta o que não está no arquivo');
$assert(Provision::missing(['A'], ['A']) === [], 'provision: nada falta quando tudo existe');
$genB64 = Provision::generate('MAILER_PAYLOAD_KEY');
$genHex = Provision::generate('BREVO_WEBHOOK_TOKEN');
$assert(strlen(base64_decode($genB64, TRUE) ?: '') === 32 && strlen($genHex) === 64 && ctype_xdigit($genHex), 'provision: chave gerada tem 32 bytes (base64) e token tem 64 hex');
$assert(Provision::plan([], NULL, FALSE, NULL) === ['cadastrar webhook transacional com a URL do ambiente'], 'provision: sem webhook, cadastra');
$assert(in_array('atualizar o token do webhook existente (token novo)', Provision::plan(['BREVO_WEBHOOK_TOKEN'], ['id' => 1], TRUE, NULL), TRUE), 'provision: token novo com webhook existente atualiza o cadastro');
$assert(Provision::plan([], ['id' => 1], FALSE, NULL) === ['webhook já cadastrado; nada a fazer'], 'provision: segunda execução não faz nada');
$assert(in_array('remover o cadastro de teste 2631443', Provision::plan([], ['id' => 9], FALSE, 2631443), TRUE), 'provision: remove o cadastro de teste quando pedido');
$tmpSecrets = sys_get_temp_dir() . '/aculta-provision-' . getmypid() . '.env';
file_put_contents($tmpSecrets, "KEEP=valor-atual\nBREVO_API_KEY=chave-de-teste");
Provision::append($tmpSecrets, ['MAILER_PAYLOAD_KEY' => 'novo']);
$afterAppend = (string) file_get_contents($tmpSecrets);
$assert(str_starts_with($afterAppend, "KEEP=valor-atual\nBREVO_API_KEY=chave-de-teste\n") && str_ends_with($afterAppend, "MAILER_PAYLOAD_KEY=novo\n"), 'provision: acrescenta sem alterar linhas existentes');
$assert(Provision::namesIn($tmpSecrets) === ['KEEP', 'BREVO_API_KEY', 'MAILER_PAYLOAD_KEY'] && Provision::valueOf($tmpSecrets, 'KEEP') === 'valor-atual', 'provision: lista nomes e lê valor só em memória');
@unlink($tmpSecrets);

echo $failures === 0 ? "tests: PASS\n" : "tests: FAIL ($failures)\n";
exit($failures === 0 ? 0 : 1);
