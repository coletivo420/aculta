<?php

/**
 * Barreira da decisão de raiz (`/`) dos subdomínios. Somente leitura; não inicia o Drupal.
 *
 * Decisão do responsável (2026-10-11): a raiz de cada host SERVE conteúdo (sem redirecionar). A única exceção é
 * o visitante não logado no host de conta, que vai (302) para /entrar. Este gate reprova se:
 * - o Portal redirecionar a raiz de algum purpose além da conta (MAIN, SUPPORT, WIKI, SHOP, MAGAZINE, COURSES);
 * - a regra de conta deixar de apontar para o login (user.login) em /entrar;
 * - a decisão não estiver registrada em docs/portal/REDIRECTS.md.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$subscriber = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php');
$docs = (string) file_get_contents($root . '/docs/portal/REDIRECTS.md');
$failures = [];
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$ok) {
    $failures[] = $message;
  }
};

// Nenhum redirecionamento de raiz para outro caminho além da conta (padrão de redirecionamento com TrustedRedirectResponse e path '/').
preg_match_all("/getPathInfo\\(\\) === '\\/'/", $subscriber, $rootChecks);
$assert(!str_contains($subscriber, "pathUrl('shop', '/loja-da-aculta')"), 'Raiz da loja redirecionando para /loja-da-aculta: a decisão é servir conteúdo na raiz.');
$processor = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/PathProcessor/PurposeFrontPathProcessor.php');
$assert(str_contains($processor, '_disable_route_normalizer') && str_contains($processor, 'FRONT_ATTRIBUTE'), 'PurposeFrontPathProcessor sem _disable_route_normalizer/FRONT_ATTRIBUTE.');
foreach (["'support' => '/apoio'", "'wiki' => '/wiki'", "'courses' => '/cursos'", "'shop' => '/loja-da-aculta'", "'forum' => '/forum'"] as $entry) {
  $assert(str_contains($processor, $entry), "Mapa FRONT_PATHS sem $entry: a raiz do host não está uniformizada.");
}
$assert(!str_contains($subscriber, "pathUrl('main', '/')") && !str_contains($subscriber, "pathUrl('support', '/')"), 'Raiz de MAIN ou SUPPORT redirecionada: a decisão é servir conteúdo.');

// A exceção da conta continua valendo.
$assert(str_contains($subscriber, "'aculta_portal.dashboard'") && str_contains($subscriber, "routeUrl('account', 'user.login')"), 'Regra da conta (visitante na raiz vai ao login) ausente.');

// A decisão está registrada na documentação de redirecionamentos.
$assert(str_contains($docs, 'serve conteúdo'), 'docs/portal/REDIRECTS.md não registra a decisão "a raiz serve conteúdo".');

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "ROOT POLICY FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("ROOT POLICY: FAIL (%d failures in %d checks)\n", count($failures), $checks));
  exit(1);
}
echo sprintf("ROOT POLICY: PASS (%d checks)\n", $checks);
