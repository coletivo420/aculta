<?php

/**
 * Gate: endereços dependentes de ambiente só existem no mapeamento do aculta_deployer.
 *
 * Regra permanente (AGENTS.md, seção "Endereços dependentes de ambiente"): domínio, subdomínio, URL absoluta, redirect
 * entre hosts, back_url/webhook de gateway, link de e-mail, origem CORS/trusted_host e callback OAuth nunca ficam
 * fixos no código, nos templates, nos assets ou na configuração exportada. Os arquivos que contêm um host de
 * produção (aculta.org) ou de teste (aculta.toca.net.br) devem estar listados em environment_bound do
 * aculta_deployer/config/deploy.json. Falha se algum arquivo fora dessa lista contiver um desses hosts.
 *
 * Fora do escopo (ferramenta e documentação, não execução): o próprio aculta_deployer, docs/, CHANGELOG, scripts/
 * (verificações de ambiente) e arquivos de ambiente em web/sites/.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$mapping = json_decode((string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'), TRUE);
if (!is_array($mapping) || !isset($mapping['environment_bound'])) {
  fwrite(STDERR, "ENVIRONMENT ADDRESSES FAIL: deploy.json sem environment_bound\n");
  exit(1);
}
$declared = array_flip(array_column($mapping['environment_bound'], 'file'));

$scanRoots = [
  'web/modules/custom/aculta_portal',
  'web/themes/custom/aculta420',
  'config/sync',
];
$skipDirs = ['web/modules/custom/aculta_portal/modules/aculta_deployer'];
$extensions = '/\.(php|module|inc|yml|twig|js|mjs|css|json|html)$/';
$hostPattern = '/(?<![A-Za-z0-9-])(?:[a-z0-9-]+\.)*(?:aculta\.toca\.net\.br|aculta\.org)(?![A-Za-z0-9-])/i';

$failures = [];
$checks = 0;
foreach ($scanRoots as $scan) {
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $scan, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !preg_match($extensions, $file->getFilename())) {
      continue;
    }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if (array_filter($skipDirs, static fn($dir) => str_starts_with($relative, $dir . '/'))) {
      continue;
    }
    $checks++;
    $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $number => $line) {
      if (!preg_match($hostPattern, $line, $m)) {
        continue;
      }
      if (!isset($declared[$relative])) {
        $failures[] = sprintf('%s:%d endereço de ambiente fixo "%s" (declare em environment_bound do aculta_deployer ou use a API de domínio)', $relative, $number + 1, $m[0]);
      }
    }
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "ENVIRONMENT ADDRESSES FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("ENVIRONMENT ADDRESSES: FAIL (%d ocorrências em %d arquivos)\n", count($failures), $checks));
  exit(1);
}

echo sprintf("ENVIRONMENT ADDRESSES: PASS (%d arquivos verificados; endereços de ambiente só no mapeamento do aculta_deployer)\n", $checks);
