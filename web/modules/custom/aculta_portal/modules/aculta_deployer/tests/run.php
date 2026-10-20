<?php

declare(strict_types=1);

// Testes determinísticos da CLI. Não usam Drupal, Drush nem vendor.
$toolRoot = dirname(__DIR__);
foreach (['Registry', 'Transform', 'Boundary', 'Verify', 'Indexing', 'HostingerLayout', 'Cli'] as $class) {
  require_once $toolRoot . '/src/' . $class . '.php';
}

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

$config = json_decode((string) file_get_contents($toolRoot . '/config/deploy.json'), true, 512, JSON_THROW_ON_ERROR);
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
file_put_contents($tmp, json_encode(['schema' => 1, 'entries' => [[
  'id' => 'DEP-0001', 'kind' => 'canonical', 'status' => 'open', 'blocking' => true,
  'page' => 'p', 'current' => 'c', 'expected_production' => 'e', 'reason' => 'r', 'owner' => 'o', 'decision' => 'pendente',
]]]));
$reg = new Registry($tmp);
$assert($reg->validate() === [], 'registro válido não gera erros');
$assert(count($reg->openBlocking()) === 1, 'entrada bloqueante aberta é contada');
$id = $reg->add(['kind' => 'sitemap', 'page' => 'p2', 'current' => 'c', 'expected_production' => 'e', 'reason' => 'r', 'owner' => 'o', 'blocking' => false]);
$assert($id === 'DEP-0002', 'novo registro recebe o próximo id');
file_put_contents($tmp, json_encode(['schema' => 1, 'entries' => [['id' => 'X1', 'kind' => 'bogus', 'status' => 'open']]]));
$assert(count((new Registry($tmp))->validate()) >= 3, 'registro com id, kind e campos inválidos é reprovado');
try {
  (new Registry($tmp))->add(['kind' => 'canonical', 'page' => 'p', 'current' => 'c', 'expected_production' => 'e', 'reason' => 'r', 'owner' => 'o']);
  $assert(false, 'add recusa registro inválido');
} catch (\InvalidArgumentException) {
  $assert(true, 'add recusa registro inválido');
}
@unlink($tmp);

// Fronteiras: o código atual passa, e referência proibida é detectada.
$repo = dirname($toolRoot, 6);
$rules = json_decode((string) file_get_contents($toolRoot . '/config/boundary.json'), true, 512, JSON_THROW_ON_ERROR);
$assert((new Boundary($repo, $toolRoot, $rules))->check() === [], 'fronteiras atuais passam');
$probe = $toolRoot . '/src/__probe.php';
// The probe is assembled at runtime so this file never contains the forbidden term.
$needle = 'Dru' . 'pal' . '::';
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
$threw = false;
try {
  new Registry($corrupt);
} catch (\JsonException) {
  $threw = true;
}
$assert($threw, 'registro com JSON corrompido gera erro explícito');
file_put_contents($corrupt, json_encode(['schema' => 1, 'entries' => []]));
$reg2 = new Registry($corrupt);
$reg2->add(['kind' => 'other', 'page' => 'p', 'current' => 'c', 'expected_production' => 'e', 'reason' => 'r', 'owner' => 'o']);
$assert(!file_exists($corrupt . '.tmp-' . getmypid()), 'gravação atômica não deixa arquivo temporário');
$assert((new Registry($corrupt))->entries()[0]['id'] === 'DEP-0001', 'registro gravado é relido com o id criado');
array_map('unlink', glob($tmpDir . '/*') ?: []);
@rmdir($tmpDir);

// Fase 2: cobertura e segurança do build (executa a CLI real em diretórios temporários).
$base = sys_get_temp_dir() . '/aculta-deployer-p2-' . getmypid();
@mkdir($base);
$cli = new AcultaDeployer\Cli($toolRoot);
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
$ra = json_decode((string) file_get_contents($base . '/a/deploy-report.json'), true);
$rb = json_decode((string) file_get_contents($base . '/b/deploy-report.json'), true);
$assert($ra['files'] === $rb['files'] && $ra['dropped'] === $rb['dropped'], 'build é idempotente: hashes e remoções iguais');
$leak = false;
foreach ($ra['files'] as $f) {
  if (str_contains((string) file_get_contents($base . '/a/' . $f['path']), 'toca.net.br')) {
    $leak = true;
  }
}
$assert(!$leak, 'nenhum arquivo gerado contém host de teste');
$assert($quiet(fn() => $cli->run(['x', 'build', '--out=' . $base . '/a', '--allow-open-blocking'])) === 1, 'build não sobrescreve saída existente');
$assert($quiet(fn() => $cli->run(['x', 'build', '--out=' . dirname($toolRoot) . '/dentro-do-repo', '--allow-open-blocking'])) === 1, 'build recusa saída dentro do repositório');
@symlink($repo . '/config', $base . '/link-para-repo');
$assert($quiet(fn() => $cli->run(['x', 'build', '--out=' . $base . '/link-para-repo/saida', '--allow-open-blocking'])) === 1, 'build recusa saída por link simbólico para o repositório');
$assert($quiet(fn() => $cli->run(['x', 'build', '--out=' . $base . '/c'])) === 2, 'build com entradas bloqueantes exige --allow-open-blocking');
// Limpeza.
foreach (['a', 'b'] as $d) {
  // Um build que falhou pode não ter criado o diretório: a limpeza só remove o que existe.
  if (!is_dir($base . '/' . $d)) {
    continue;
  }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/' . $d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($it as $f) {
    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
  }
  rmdir($base . '/' . $d);
}
@unlink($base . '/link-para-repo');
@rmdir($base);

// Layout Hostinger: instalação permanece na pasta do domínio; public_html só aponta para web/.
$domainRoot = sys_get_temp_dir() . '/aculta-hostinger-layout-' . getmypid();
mkdir($domainRoot . '/web/sites/default', 0775, true);
mkdir($domainRoot . '/vendor', 0775, true);
file_put_contents($domainRoot . '/composer.json', '{}');
file_put_contents($domainRoot . '/composer.lock', '{}');
file_put_contents($domainRoot . '/vendor' . '/autoload.php', '<?php');
file_put_contents($domainRoot . '/web/index.php', '<?php');
file_put_contents($domainRoot . '/web/sites/default/settings.php', '<?php');
mkdir($domainRoot . '/public_html');
file_put_contents($domainRoot . '/public_html/index.html', 'site anterior');
$layout = new AcultaDeployer\HostingerLayout();
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
$thrown = false;
try {
  $layout->inspect($incomplete);
} catch (RuntimeException) {
  $thrown = true;
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
$removeFixture = null;
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
  ['kind' => 'other', 'page' => 'p1', 'current' => 'c', 'expected_production' => 'e', 'reason' => 'r', 'owner' => 'o', 'extra' => 'ignorado'],
  ['kind' => 'link', 'page' => 'p2', 'current' => 'c', 'expected_production' => 'e', 'reason' => 'r', 'owner' => 'o'],
];
$gReg = new Registry($regFile);
$ids = $gReg->addMany($good);
$assert($ids === ['DEP-0001', 'DEP-0002'] && !array_key_exists('extra', (new Registry($regFile))->entries()[0]), 'importação grava achados válidos e descarta campos extras');
$badFindings = [$good[0], ['kind' => 'canonical', 'page' => 'p3']];
$before = file_get_contents($regFile);
$threwBad = false;
try {
  (new Registry($regFile))->addMany($badFindings);
} catch (\InvalidArgumentException) {
  $threwBad = true;
}
$assert($threwBad && file_get_contents($regFile) === $before, 'importação com um achado inválido não grava nada');
unlink($regFile);

// Fase 5: verificação pós-deploy somente leitura.
$assert(AcultaDeployer\Verify::isAllowedUrl('https://apoio.aculta.org/'), 'verify aceita URL https válida');
$assert(!AcultaDeployer\Verify::isAllowedUrl('http://apoio.aculta.org/'), 'verify recusa http');
$assert(!AcultaDeployer\Verify::isAllowedUrl('https://user:senha@apoio.aculta.org/'), 'verify recusa URL com credenciais');
$assert(!AcultaDeployer\Verify::isAllowedUrl('https://'), 'verify recusa URL sem host');
$assert(AcultaDeployer\Verify::evaluate('<link rel="canonical" href="https://apoio.aculta.org/">', '<link rel="canonical" href="https://apoio.aculta.org/">'), 'verify encontra o valor esperado no corpo');
$assert(!AcultaDeployer\Verify::evaluate('<link rel="canonical" href="https://apoio.aculta.toca.net.br/">', '<link rel="canonical" href="https://apoio.aculta.org/">'), 'verify reprova canonical de teste');
$assert(!AcultaDeployer\Verify::evaluate('qualquer coisa', ''), 'verify reprova expectativa vazia');
$regPath = $toolRoot . '/registry/deploy-registry.json';
$before = hash_file('sha256', $regPath);
ob_start();
$rcVerify = (new AcultaDeployer\Cli($toolRoot))->run(['x', 'verify']);
ob_end_clean();
$assert($rcVerify === 1 || $rcVerify === 0, 'verify executa e retorna código de saída válido');
$assert(hash_file('sha256', $regPath) === $before, 'verify não altera o registro (somente leitura)');

// Política de indexação: produção indexável; teste não indexável.
$assert(AcultaDeployer\Verify::robotsHeader(['HTTP/1.1 200 OK', 'X-Robots-Tag: noindex, nofollow']) === 'noindex, nofollow', 'lê X-Robots-Tag do cabeçalho (case-insensitive)');
$assert(AcultaDeployer\Verify::robotsHeader(['HTTP/1.1 200 OK', 'Content-Type: text/html']) === null, 'cabeçalho ausente resulta em null');
$assert(AcultaDeployer\Verify::isNoindex('noindex, nofollow, noarchive') && !AcultaDeployer\Verify::isNoindex('index, follow') && !AcultaDeployer\Verify::isNoindex(null), 'detecta noindex e aceita ausência');
$pol = $config['robots_policy'] ?? [];
$assert(array_key_exists('x_robots_tag', $pol['production'] ?? []) && $pol['production']['x_robots_tag'] === null, 'política de produção não envia X-Robots-Tag');
$assert(str_contains((string) ($pol['test']['x_robots_tag'] ?? ''), 'noindex'), 'política de teste envia noindex');
$prodHosts = $pol['production']['hosts'] ?? [];
$expectedProd = ['https://aculta.org/', 'https://conta.aculta.org/', 'https://apoio.aculta.org/', 'https://revista420.aculta.org/', 'https://wiki420.aculta.org/', 'https://loja.aculta.org/', 'https://cursos.aculta.org/'];
$assert(sort($prodHosts) === true && $prodHosts === (function () use ($expectedProd) { sort($expectedProd); return $expectedProd; })(), 'todos os sete domínios de produção estão na política (um por purpose)');
$assert(!array_filter($prodHosts, static fn($h) => str_contains($h, 'toca.net.br')), 'nenhum host de teste na política de produção');
$badRobots = ['robots_policy' => ['production' => ['x_robots_tag' => 'noindex']]];
$guard = AcultaDeployer\Verify::isNoindex($badRobots['robots_policy']['production']['x_robots_tag']);
$assert($guard === true, 'trava de build detecta política de produção com noindex');

$assert(AcultaDeployer\Verify::statusCode(['HTTP/1.1 403 Forbidden', 'Content-Type: text/html']) === 403, 'lê o código HTTP da resposta');
$assert(AcultaDeployer\Verify::statusCode(['Content-Type: text/html']) === null, 'sem linha de status resulta em null');
$assert(AcultaDeployer\Verify::metaNoindex('<html><head><meta name="robots" content="noindex" /></head></html>') === true, 'detecta meta robots noindex');
$assert(AcultaDeployer\Verify::metaNoindex('<meta content="noindex, nofollow" name="ROBOTS">') === true, 'meta robots aceita atributos em outra ordem e caixa');
$assert(AcultaDeployer\Verify::metaNoindex('<meta name="description" content="noindex">') === false, 'meta de outro nome não conta como robots');
$assert(AcultaDeployer\Verify::metaNoindex('<html><body>sem meta</body></html>') === false, 'página sem meta robots não é noindex');
$assert(AcultaDeployer\Verify::isRefusedStatus(404) && AcultaDeployer\Verify::isRefusedStatus(403) && !AcultaDeployer\Verify::isRefusedStatus(200) && !AcultaDeployer\Verify::isRefusedStatus(null), 'status recusado/inexistente aceito; 200 e ausente não');
$privProbes = $pol['production']['private_probes'] ?? [];
$assert(count($privProbes) >= 9 && !array_filter($privProbes, static fn($u) => str_contains((string) $u, 'toca.net.br')), 'caminhos privados de produção listados, sem host de teste');
$assert(in_array('https://conta.aculta.org/entrar', $privProbes, true), 'login (/entrar) está entre os caminhos privados');

$assert(AcultaDeployer\Verify::xmlLocs('<urlset><url><loc>https://aculta.org/a</loc></url><url><loc> https://aculta.org/b </loc></url></urlset>') === ['https://aculta.org/a', 'https://aculta.org/b'], 'lê todos os <loc> e ignora espaços');
$assert(AcultaDeployer\Verify::xmlLocs('<sitemapindex><sitemap><loc>https://aculta.org/main/sitemap.xml</loc></sitemap></sitemapindex>') === ['https://aculta.org/main/sitemap.xml'], 'lê o índice de sitemaps');
$assert(AcultaDeployer\Verify::xmlLocs('<broken') === null, 'XML inválido resulta em null');
$assert(AcultaDeployer\Verify::xmlLocs('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><urlset><url><loc>&e;</loc></url></urlset>') !== ['root:'], 'entidades externas não são resolvidas (XXE)');
$assert(AcultaDeployer\Verify::sitemapDirectives("User-agent: *\nSitemap: https://aculta.org/sitemap.xml\nsitemap: https://x.org/y.xml\n") === ['https://aculta.org/sitemap.xml', 'https://x.org/y.xml'], 'lê diretivas Sitemap sem diferenciar caixa');
$assert(AcultaDeployer\Verify::sitemapDirectives("Disallow: /admin/\n") === [], 'robots sem Sitemap não gera diretiva');
$assert(AcultaDeployer\Verify::disallowsRoot("User-agent: *\nDisallow: /\n") === true, 'detecta Disallow: / (site inteiro bloqueado)');
$assert(AcultaDeployer\Verify::disallowsRoot("User-agent: *\nDisallow: /admin/\nDisallow:\n") === false, 'Disallow de caminho específico e Disallow vazio não bloqueiam o site');
$assert(AcultaDeployer\Verify::testEquivalent('apoio.aculta.org') === 'apoio.aculta.toca.net.br' && AcultaDeployer\Verify::testEquivalent('aculta.org') === 'aculta.toca.net.br', 'equivalente de teste preserva o subdomínio');
$assert(AcultaDeployer\Verify::testEquivalent('evil-aculta.org') === 'evil-aculta.org', 'equivalente de teste troca só o sufixo exato');
$assert(AcultaDeployer\Verify::hostOf('https://APOIO.aculta.org/x?y=1') === 'apoio.aculta.org', 'hostOf normaliza a caixa e ignora caminho e query');
$sm = $config['sitemap'] ?? [];
$assert(AcultaDeployer\Verify::hostOf($sm['production']['index_url'] ?? '') === AcultaDeployer\Verify::hostOf($sm['production']['index_base'] ?? '1'), 'índice de produção está na base de produção');
$assert(AcultaDeployer\Verify::hostOf($sm['test']['index_url'] ?? '') === AcultaDeployer\Verify::hostOf($sm['test']['index_base'] ?? '1') && !str_contains((string) ($sm['production']['index_url'] ?? ''), 'toca.net.br'), 'índice de teste na base de teste; produção sem host de teste');
$robotsWeb = (string) file_get_contents(dirname(__DIR__, 6) . '/robots.txt');
$assert(in_array($sm['production']['index_url'] ?? '', AcultaDeployer\Verify::sitemapDirectives($robotsWeb), true), 'web/robots.txt anuncia o índice de produção (mesma URL da política)');
$assert(!AcultaDeployer\Verify::disallowsRoot($robotsWeb), 'web/robots.txt não bloqueia o site inteiro');

$contract = json_decode((string) file_get_contents(dirname($toolRoot, 6) . '/config/secrets-contract.json'), true);
$doc = (string) file_get_contents(dirname($toolRoot, 6) . '/docs/operations/SECRETS.md');
$docOk = true;
foreach (array_column($contract['variables'], 'name') as $n) { if (!str_contains($doc, '`' . $n . '`')) { $docOk = false; } }
$assert($docOk, 'todo nome do contrato aparece na tabela de SECRETS.md (gate anti-regressão do contrato)');
$requiredProd = array_column(array_filter($contract['variables'], static fn($v) => in_array('production', $v['required_in'], true)), 'name');
$assert(array_diff($requiredProd, array_column($contract['variables'], 'name')) === [], 'obrigatórios de produção estão no contrato');
$cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($toolRoot . '/bin/aculta-deployer');
$rfDir = sys_get_temp_dir() . '/aculta-report-' . getmypid();
@mkdir($rfDir, 0700);
$rfOut = $rfDir . '/status.json';
exec($cli . ' report --out=' . escapeshellarg($rfOut) . ' 2>&1', $o7, $rc7);
$rfText = is_file($rfOut) ? (string) file_get_contents($rfOut) : '';
$rfData = json_decode($rfText, true);
$assert($rc7 === 0 && is_array($rfData) && ($rfData['schema'] ?? null) === 1, 'report grava JSON com esquema 1');
$o8 = []; exec($cli . ' report --out=' . escapeshellarg($rfOut) . ' 2>&1', $o8, $rc8);
$assert($rc8 === 0, 'report sobrescreve o próprio relatório (publicação atômica)');
@unlink($rfOut); @rmdir($rfDir);

// Fronteira: pasta de leitura neutra do Portal pode citar a ferramenta, mas não executá-la.
$bRoot = sys_get_temp_dir() . '/aculta-boundary-' . getmypid();
@mkdir($bRoot . '/web/modules/custom/aculta_portal/src/Deployer', 0700, true);
@mkdir($bRoot . '/web/modules/custom/aculta_portal/src/Other', 0700, true);
file_put_contents($bRoot . '/web/modules/custom/aculta_portal/src/Deployer/Ok.php', "<?php // texto: aculta-deployer report\n");
file_put_contents($bRoot . '/web/modules/custom/aculta_portal/src/Deployer/Exec.php', "<?php shell_exec('x');\n");
file_put_contents($bRoot . '/web/modules/custom/aculta_portal/src/Other/Bad.php', "<?php // aculta-deployer\n");
$rules = json_decode((string) file_get_contents($toolRoot . '/config/boundary.json'), true);
$rules['consumers'] = ['web/modules/custom/aculta_portal'];
$bv = (new AcultaDeployer\Boundary($bRoot, $toolRoot, $rules))->check();
$joined = implode("\n", $bv);
$assert(str_contains($joined, 'Exec.php') && !str_contains($joined, 'Ok.php'), 'fronteira: pasta neutra aceita citação e recusa execução');
$assert(str_contains($joined, 'Bad.php'), 'fronteira: citação da ferramenta fora da pasta neutra é recusada');
exec('rm -rf ' . escapeshellarg($bRoot));

// Ambiente do site: grava test/production com o endereço certo; recusa valor desconhecido; restaura o arquivo real.
$envFile = dirname($toolRoot, 6) . '/var/deployer/environment.json';
$envBefore = is_file($envFile) ? (string) file_get_contents($envFile) : null;
exec($cli . ' environment set --to=production 2>&1', $e1, $erc1);
$envProd = json_decode((string) file_get_contents($envFile), true);
$assert($erc1 === 0 && ($envProd['environment'] ?? null) === 'production' && ($envProd['site'] ?? null) === 'https://aculta.org', 'environment set production grava o endereço de produção');
exec($cli . ' environment set --to=xx 2>&1', $e2, $erc2);
$assert($erc2 !== 0 && (json_decode((string) file_get_contents($envFile), true)['environment'] ?? null) === 'production', 'environment recusa valor desconhecido e mantém o atual');
exec($cli . ' environment set --to=test 2>&1', $e3, $erc3);
$envTest = json_decode((string) file_get_contents($envFile), true);
$assert($erc3 === 0 && ($envTest['site'] ?? null) === 'https://aculta.toca.net.br', 'environment set test grava o endereço de teste');
if ($envBefore === null) { @unlink($envFile); } else { file_put_contents($envFile, $envBefore); }

// Verify no ambiente de teste: hosts de produção viram os de teste; host parecido não muda.
$assert(AcultaDeployer\Verify::toTestEnvironment('<link rel="canonical" href="https://apoio.aculta.org/">') === '<link rel="canonical" href="https://apoio.aculta.toca.net.br/">', 'verify teste: canonical de produção vira o host de teste');
$assert(AcultaDeployer\Verify::toTestEnvironment('https://evil-aculta.org/ https://aculta.org/x') === 'https://evil-aculta.org/ https://aculta.toca.net.br/x', 'verify teste: host parecido com produção não é alterado');
// Perfis por ambiente: o deploy.json atual é válido e cobre todos os arquivos dependentes de ambiente.
$assert($t->validateProfiles() === [], 'perfis: deploy.json atual cobre todos os arquivos dependentes de ambiente');
// Guardrail: um arquivo dependente de ambiente sem regra em um perfil falha a validação.
$broken = $config;
$broken['profiles']['test']['rules'] = array_values(array_filter($config['profiles']['test']['rules'], static fn(array $r): bool => $r['file'] !== 'config/sync/system.site.yml'));
$assert(count(array_filter((new Transform($broken))->validateProfiles(), static fn(string $e): bool => str_contains($e, 'system.site.yml'))) === 1, 'perfis: arquivo sem regra no perfil de teste é recusado');
$noBound = $config;
$noBound['environment_bound'] = [];
$assert((new Transform($noBound))->validateProfiles() !== [], 'perfis: environment_bound vazio é recusado');
// Aplicação de regra: exatamente uma linha casa; zero ou várias recusam o build.
$assert($t->applyProfile("exemplo_on: false\nx: 1\n", [['pattern' => '^exemplo_on: .*$', 'with' => 'exemplo_on: true']], 'f') === "exemplo_on: true\nx: 1\n", 'perfis: regra troca a linha casada');
$threw = static function (callable $fn): bool { try { $fn(); return false; } catch (\RuntimeException $e) { return true; } };
// Indexação (robots): resumo neutro do último resultado, lido pelo painel do Portal.
$indexRun = \AcultaDeployer\Indexing::build('production', [
  ['kind' => 'host', 'url' => 'https://aculta.org/', 'ok' => true, 'observed' => 'ausente'],
  ['kind' => 'host', 'url' => 'https://loja.aculta.org/', 'ok' => false, 'observed' => 'noindex'],
], false, '2026-10-11T12:00:00+00:00');
$sum = \AcultaDeployer\Indexing::summarize($indexRun);
$assert($sum !== null && $sum['passed'] === 1 && $sum['failed'] === 1 && $sum['failed_urls'] === ['https://loja.aculta.org/'] && $sum['ok'] === false && $sum['environment'] === 'production', 'indexação: resumo conta aprovados e falhas e lista só as URLs com falha');
$assert(\AcultaDeployer\Indexing::summarize(null) === null && \AcultaDeployer\Indexing::summarize(['schema' => 2]) === null, 'indexação: arquivo ausente ou de outro schema não gera resumo');
$assert(\AcultaDeployer\Indexing::summarize(\AcultaDeployer\Indexing::build('test', [], true, 'x'))['ok'] === true, 'indexação: resultado sem checks e ok gera resumo válido');
$assert($threw(fn() => $t->applyProfile("x: 1\n", [['pattern' => '^exemplo_on: .*$', 'with' => 'exemplo_on: true']], 'f')), 'perfis: regra sem linha correspondente falha');
$assert($threw(fn() => $t->applyProfile("a\na\n", [['pattern' => '^a$', 'with' => 'b']], 'f')), 'perfis: regra ambígua (duas linhas) falha');
// Build de teste: gera a saída do ambiente de teste (hosts de teste preservados, SMTP no system.mail).
$outTest = sys_get_temp_dir() . '/aculta-build-test-' . getmypid();
exec($cli . ' build --target=test --out=' . escapeshellarg($outTest) . ' 2>&1', $bt, $btrc);
$mailTest = is_file($outTest . '/config/sync/system.mail.yml') ? (string) file_get_contents($outTest . '/config/sync/system.mail.yml') : '';
$assert($btrc === 0 && str_contains($mailTest, '  default: aculta_mailer'), 'build --target=test: system.mail na camada de envio E-Mails ACULTA');
$assert(!is_file($outTest . '/config/sync/smtp.settings.yml'), 'build --target=test: smtp.settings legado não é gerado');
// Alvo e ambiente divergentes são recusados sem gerar saída.
exec($cli . ' build --env=test --target=production --out=' . escapeshellarg(sys_get_temp_dir() . '/aculta-build-' . getmypid()) . ' 2>&1', $b1, $brc1);
$assert($brc1 !== 0, 'build recusa --env diferente de --target');

// Tabela de migração de domínios: somente leitura; a revista está alinhada ao host decidido.
exec($cli . ' domains 2>&1', $dom, $domrc);
$assert($domrc === 0 && str_contains(implode("\n", $dom), 'revista420.aculta.org (regra de host)') && str_contains(implode("\n", $dom), '0 pendente(s) de RC'), 'domains: tabela válida; revista alinhada (host de produção decidido)');

echo $failures === 0 ? "tests: PASS\n" : "tests: FAIL ($failures)\n";
exit($failures === 0 ? 0 : 1);
