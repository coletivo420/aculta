<?php

/**
 * Gate: cookie de sessão por ambiente (aculta_deployer, seção session_cookie) continua batendo com os arquivos de serviços.
 *
 * Barreira para alterações futuras de cookie: se o services.homelab.yml (teste) ou o modelo de produção
 * (services.hostinger.yml.example) mudar o cookie_domain sem atualizar config/deploy.json, este gate falha.
 * Roda o comando somente leitura `aculta-deployer cookies`. Não consulta o Runtime nem o servidor de produção.
 * Regra e transição: web/modules/custom/aculta_portal/modules/aculta_deployer/docs/COOKIES-AMBIENTE.md.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$cli = $root . '/web/modules/custom/aculta_portal/modules/aculta_deployer/bin/aculta-deployer';

if (!is_file($cli)) {
  echo "SESSION COOKIE POLICY GATE: FAIL\n - aculta-deployer ausente em $cli\n";
  exit(1);
}

$output = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($cli) . ' cookies 2>&1', $output, $code);

if ($code !== 0) {
  echo "SESSION COOKIE POLICY GATE: FAIL\n";
  foreach ($output as $line) {
    echo ' ' . $line . "\n";
  }
  exit(1);
}
echo "SESSION COOKIE POLICY GATE: PASS (cookie de sessão por ambiente confere com config/deploy.json)\n";
