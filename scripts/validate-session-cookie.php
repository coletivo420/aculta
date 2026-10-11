<?php

/**
 * Gate do cookie de sessão compartilhado entre os hosts do Portal. Somente leitura.
 *
 * Cada ambiente define session.storage.options.cookie_domain com o domínio-base (ponto inicial, para valer em
 * todos os subdomínios) e cookie_samesite Lax (docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md). Reprova se:
 * - o arquivo de teste (services.homelab.yml) não tiver o domínio-base de teste;
 * - o modelo de produção (services.hostinger.yml.example) não tiver o domínio-base de produção;
 * - o SameSite não for Lax em nenhum dos dois.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__) . '/web/sites/default';
$environments = [
  'teste' => ['file' => $root . '/services.homelab.yml', 'domain' => '.aculta.toca.net.br'],
  'producao' => ['file' => $root . '/services.hostinger.yml.example', 'domain' => '.aculta.org'],
];

$failures = [];
$checks = 0;
foreach ($environments as $name => $env) {
  $checks++;
  if (!is_file($env['file'])) {
    $failures[] = "$name: arquivo ausente ({$env['file']}).";
    continue;
  }
  $data = Yaml::parseFile($env['file']);
  $options = $data['parameters']['session.storage.options'] ?? [];

  $checks++;
  if (($options['cookie_domain'] ?? NULL) !== $env['domain']) {
    $failures[] = "$name: cookie_domain deveria ser {$env['domain']}, está " . var_export($options['cookie_domain'] ?? NULL, TRUE) . '.';
  }
  $checks++;
  if (($options['cookie_samesite'] ?? NULL) !== 'Lax') {
    $failures[] = "$name: cookie_samesite deveria ser Lax.";
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "SESSION COOKIE FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("SESSION COOKIE: FAIL (%d failures in %d checks)\n", count($failures), $checks));
  exit(1);
}
echo sprintf("SESSION COOKIE: PASS (%d checks)\n", $checks);
