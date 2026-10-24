<?php

// Gate anti-regressão: caminhos internos de raiz devem responder 404 (sem 301), e a política deve estar ligada ao assinante.
// Uso: php scripts/validate-domain-front-paths.php [--live]

declare(strict_types=1);

$raiz = dirname(__DIR__);
$falhas = [];
$ok = [];
$check = static function (bool $cond, string $msg) use (&$falhas, &$ok): void {
  $cond ? ($ok[] = $msg) : ($falhas[] = $msg);
};

$politica = $raiz . '/web/modules/custom/aculta_portal/src/Domain/FrontPathPolicy.php';
$assinante = $raiz . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php';
$processador = $raiz . '/web/modules/custom/aculta_portal/src/PathProcessor/PurposeFrontPathProcessor.php';
$doc = $raiz . '/docs/portal/DOMAIN-FRONT-PATHS.md';

$check(is_file($politica), 'FrontPathPolicy existe');
$check(is_file($doc), 'documento DOMAIN-FRONT-PATHS.md existe');

$textoAssinante = is_file($assinante) ? file_get_contents($assinante) : '';
$check(str_contains($textoAssinante, 'FrontPathPolicy::bloqueiaCaminhoInterno'), 'assinante usa FrontPathPolicy');
$check(str_contains($textoAssinante, 'throw new NotFoundHttpException()'), 'assinante responde 404 para caminho interno');

// Somente o retorno/URL antiga de checkout conserva 301 enquanto a
// integração com pagamentos não for auditada. /minha-conta responde 404.
$quantos301 = substr_count($textoAssinante, 'HTTP_MOVED_PERMANENTLY');
$check($quantos301 === 1, "assinante tem somente o 301 de checkout funcional pendente de auditoria; encontrado: $quantos301");

// Mapa de caminhos internos: documento e código devem listar os mesmos caminhos.
$textoProcessador = is_file($processador) ? file_get_contents($processador) : '';
preg_match_all("/'([a-z_]+)' => '(\/[a-z0-9\/-]+)'/", $textoProcessador, $m, PREG_SET_ORDER);
$mapa = [];
foreach ($m as $linha) {
  $mapa[$linha[1]] = $linha[2];
}
$textoDoc = is_file($doc) ? file_get_contents($doc) : '';
foreach ($mapa as $purpose => $caminho) {
  $check(str_contains($textoDoc, "`$caminho`"), "caminho $caminho ($purpose) documentado");
}
$check(count($mapa) >= 6, 'mapa de raiz tem as seis finalidades');

// Endereços de produção não podem voltar nas sobreposições de metadados por domínio (canonical e og:url usam o token [site:url]).
$sobreposicoes = glob($raiz . '/config/sync/domain*/metatag.metatag_defaults.front.yml') ?: [];
foreach ($sobreposicoes as $arquivo) {
  $texto = file_get_contents($arquivo) ?: '';
  $check(preg_match('~(canonical_url|og_url): .https?://[a-z0-9.-]*aculta\.org~', $texto) !== 1, 'sem endereço de produção em ' . basename(dirname($arquivo)) . '/metatag front');
}
$check(count($sobreposicoes) >= 1, 'sobreposições de metatag por domínio encontradas');

if (in_array('--live', $argv, TRUE)) {
  $hosts = ['apoio', 'cursos', 'wiki420', 'revista420'];
  foreach ($hosts as $host) {
    foreach (['/apoio', '/wiki', '/cursos'] as $caminho) {
      $cod = (int) trim((string) shell_exec(sprintf('curl -sk -o /dev/null -w "%%{http_code}" --max-time 15 %s', escapeshellarg("https://$host.aculta.toca.net.br$caminho"))));
      if ($cod === 0) {
        continue;
      }
      $check($cod === 404, "$host.aculta.toca.net.br$caminho responde 404 (obtido $cod)");
    }
  }
}

foreach ($ok as $msg) {
  echo "OK  $msg\n";
}
foreach ($falhas as $msg) {
  echo "FAIL $msg\n";
}
echo $falhas === [] ? "DOMAIN FRONT PATHS: PASS (" . count($ok) . " checks)\n" : "DOMAIN FRONT PATHS: FAIL (" . count($falhas) . ")\n";
exit($falhas === [] ? 0 : 1);
