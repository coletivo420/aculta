<?php

/**
 * Gate do endereço da conta (Minha conta > Meus dados > Endereço). Tolerância zero.
 *
 * Protege o comportamento aprovado pelo responsável (2026-10-18). Cada regra abaixo corresponde a uma regressão que já
 * aconteceu. Se um desses trechos sumir, o gate falha e a mudança não deve ser mesclada.
 *
 * Verificação completa no navegador (Firefox, X11): scripts/test-account-address-browser.py (não roda neste gate).
 */

$root = dirname(__DIR__);
$failures = 0;

$check = static function (bool $ok, string $message) use (&$failures): void {
  echo ($ok ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
  if (!$ok) {
    $failures++;
  }
};

$read = static function (string $relative) use ($root): string {
  $path = $root . '/' . $relative;
  return is_file($path) ? (string) file_get_contents($path) : '';
};

$hooks = $read('web/modules/custom/aculta_portal/src/Hook/PortalHooks.php');
$editorial = $read('web/modules/custom/aculta_portal/src/Hook/EditorialHooks.php');
$cep = $read('web/modules/custom/aculta_portal/modules/aculta_portal_cep/js/cep-address.js');
$controller = $read('web/modules/custom/aculta_portal/modules/aculta_portal_cep/src/Controller/CepLookupController.php');
$fieldConfig = $read('config/sync/field.field.profile.authenticated.field_address.yml');

// 1. O bloco de papel não pode capturar /dados/endereco (authenticated é tipo de papel; isso sumiu número e país).
$check(
  (bool) preg_match("/ehTipoDePapel\\(\\\$entity\\)\\s*&&\\s*\\\$path !== '\\/dados\\/endereco'/", $hooks),
  'PortalHooks: o bloco de papel exclui /dados/endereco',
);

// 2. País fixo em Brasil: campo oculto (não #access FALSE, que faz o módulo Address descartar a entrada inteira).
$check(
  str_contains($hooks, "['#type'] = 'hidden'") && !str_contains($hooks, "country_code']['#access'] = FALSE"),
  'PortalHooks: país oculto como campo hidden, sem #access FALSE',
);
$check(
  (bool) preg_match("/available_countries:\\s*\\n\\s+BR: BR\\s*\\n/", $fieldConfig),
  'config: field_address do authenticated aceita só BR',
);

// 3. Cliente ViaCEP do contrib não carregado; o Portal faz a busca com o cabeçalho que o endpoint exige.
$check(
  !str_contains($editorial, "'cep_autocomplete'") && !str_contains($cep, 'viacep'),
  'sem dependência do cep_autocomplete (módulo desinstalado e portado)',
);
$check(
  str_contains($read('web/modules/custom/aculta_portal/modules/aculta_portal_cep/aculta_portal_cep.routing.yml'), "path: '/aculta_portal_cep/{cep}'"),
  'rota de consulta de CEP em /aculta_portal_cep/{cep}',
);
$check(
  str_contains($cep, "'X-Requested-With': 'XMLHttpRequest'") || str_contains($cep, 'X-Requested-With'),
  'cep-address.js: envia X-Requested-With',
);
$check(
  str_contains($controller, 'isXmlHttpRequest()'),
  'CepLookupController: endpoint continua exigindo AJAX',
);

// 4. Troca de CEP: o CEP resolvido fica por campo (sobrevive ao AJAX) e a troca escreve o endereço inteiro.
$check(str_contains($cep, 'const resolvedCep = new Map()'), 'cep-address.js: CEP resolvido guardado por campo');
$check(str_contains($cep, 'function keysToWrite') && str_contains($cep, 'if (replace) return values;'), 'cep-address.js: troca de CEP substitui o endereço');
$check(str_contains($cep, 'pendingFill.delete(prefix)'), 'cep-address.js: preenchimento pendente vale só para o próprio rebuild');

// 5. Alias `address` nos perfis: campo computado, sem armazenamento, lendo o field_address (o contrib do Mercado Pago lê
// `address`). Campo com armazenamento, ou cópia, seria dado duplicado.
$alias = $read('web/modules/custom/aculta_portal/src/Hook/ProfileAddressAliasHooks.php');
$aliasList = $read('web/modules/custom/aculta_portal/src/Commerce/MercadoPago/BillingAddressAliasItemList.php');
$check(str_contains($alias, "setComputed(TRUE)") && str_contains($alias, "BillingAddressAliasItemList::class"), 'alias address é campo computado (sem armazenamento)');
$check(str_contains($aliasList, "ComputedItemListTrait") && str_contains($aliasList, "'field_address'"), 'alias address lê o field_address do mesmo perfil');

// 5b. Base local de CEPs: o controlador consulta a base local antes das APIs externas, e a base não entra no Git.
$check(
  str_contains($controller, 'aculta_portal_cep.local_store') && strpos($controller, '->local->lookup(') < strpos($controller, 'fromViaCep('),
  'CepLookupController: base local consultada antes do ViaCEP/BrasilAPI',
);

// 6. Teste de navegador presente (a barreira só vale se o teste existir).
$check(is_file($root . '/scripts/test-account-address-browser.py'), 'teste de navegador do endereço presente');

echo $failures === 0 ? 'ACCOUNT ADDRESS GATE: PASS' . PHP_EOL : "ACCOUNT ADDRESS GATE: FAIL ({$failures})" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
