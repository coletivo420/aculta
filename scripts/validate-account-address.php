<?php

/**
 * @file
 * Gate do formulário unificado de endereço. Tolerância zero.
 *
 * Protege o comportamento aprovado pelo responsável. Cada regra abaixo
 * corresponde a uma regressão anterior; se falhar, a mudança não deve ser
 * mesclada.
 *
 * O navegador Firefox é executado separadamente pelo teste documentado em
 * tests/browser/aculta-theme-mobile-first/README.md.
 */

$root = dirname(__DIR__);
if (file_exists($root . '/vendor/autoload.php')) {
  require_once $root . '/vendor/autoload.php';
}
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
$accountShell = $read('web/modules/custom/aculta_portal/src/AccountShellBuilder.php');
$routes = $read('web/modules/custom/aculta_portal/aculta_portal.routing.yml');
$form = $read('web/modules/custom/aculta_portal/src/Account/AccountAddressForm.php');
$eventWizard = $read('web/modules/custom/aculta_portal/modules/aculta_events_openstreetmap/src/Form/OpenStreetMapImportWizard.php');
$accountAddressHooks = $read('web/modules/custom/aculta_portal/src/Hook/AccountAddressHooks.php');
$eventLocation = $read('web/modules/custom/aculta_portal/modules/aculta_events/src/Entity/EventLocation.php');
$osmWizard = $read('web/modules/custom/aculta_portal/modules/aculta_events_openstreetmap/src/Form/OpenStreetMapImportWizard.php');

// 1. O bloco de papel não pode capturar a edição de endereço do Portal
// NEM a rota equivalente do Studio: ambas usam o mesmo Profile Commerce.
$check(
  str_contains($hooks, "'aculta_portal.my_data_address'")
    && str_contains($hooks, "'aculta_studio.account_address'")
    && str_contains($hooks, '&& !$isAddressRoute')
    && str_contains($hooks, '&& $isAddressRoute')
    && str_contains($hooks, 'AccountAddressForm::apply('),
  'PortalHooks: ambos os editores de endereço preservam país, Número e S/N',
);
$check(
  str_contains($form, "public const LIBRARY = 'aculta_portal_cep/cep-address'")
    && str_contains($form, 'final class AccountAddressForm'),
  'decisão: implementação central de endereço no Portal usa a integração CEP oficial',
);
$check(
  str_contains($form, "'aculta_event_location'")
    && str_contains($form, "'field_endereco'")
    && str_contains($eventWizard, 'AccountAddressForm::apply(')
    && str_contains($eventWizard, 'AccountAddressForm::LIBRARY'),
  'D-54/D-55: EventLocation e revisão OSM reutilizam o adaptador e a biblioteca CEP centrais',
);
$check(
  str_contains($eventWizard, "'country_code' => 'BR'")
    && str_contains($eventWizard, "'addr:postcode'")
    && !str_contains($eventWizard, "'country_code' => 'select'"),
  'D-55: endereço de local/evento fica restrito ao Brasil, sem seletor de país',
);
$check(
  str_contains($routes, 'path: /conta-legada')
    && str_contains($routes, 'path: /dados/endereco')
    && str_contains($accountShell, "'aculta_portal.my_data_address' => 'aculta_portal.my_data'")
    && str_contains($accountShell, "'aculta_portal.my_data'"),
  'conta legada: Meus Dados → endereço canônico no shell existente',
);
$callbacks = $read('web/modules/custom/aculta_portal/src/Form/PortalFormCallbacks.php');
$check(
  str_contains($callbacks, "'aculta_studio.account_address'")
    && str_contains($callbacks, "'aculta_portal.my_data_address'")
    && str_contains($callbacks, '$formState->setRedirect($route)'),
  'PortalFormCallbacks: submissão retorna ao mesmo editor sem perder o legado',
);
// 2. País fixo em Brasil: campo oculto. #access FALSE faria Address descartar
// a entrada inteira. AccountAddressForm atende Conta, checkout, loja e equipe.
$check(
  str_contains($form, "['#type'] = 'hidden'") && !str_contains($form, "country_code']['#access'] = FALSE"),
  'AccountAddressForm: país oculto como campo hidden, sem #access FALSE',
);
$check(
  !str_contains($hooks, 'hideCountrySelect') && str_contains($hooks, 'AccountAddressForm::apply('),
  'PortalHooks: Minha conta usa o formulário único (sem tratamento de país próprio)',
);
$checkout = $read('web/modules/custom/aculta_portal/src/Plugin/Commerce/CheckoutPane/BillingAddressPane.php');
$check(
  str_contains($checkout, 'AccountAddressForm::apply('),
  'checkout: pane de cobrança usa o formulário único',
);
$accountHooks = $read('web/modules/custom/aculta_portal/src/Hook/AccountAddressHooks.php');
$check(
  str_contains($accountHooks, 'AccountAddressForm::apply(') && str_contains($form, "'commerce_store'") && str_contains($form, 'TEAM_PROFILE_TYPES'),
  'loja e perfis de equipe usam o formulário único',
);
$check(
  str_contains($form, "'aculta_event_location'") && str_contains($form, "return 'field_endereco'")
    && str_contains($accountAddressHooks, 'AccountAddressForm::apply(')
    && str_contains($form, "hasField('field_endereco') ? 'field_endereco'"),
  'entidade de local de evento usa o formulário e o entity builder centrais',
);
$check(
  str_contains($eventLocation, "->setSetting('available_countries', ['BR'])"),
  'endereço de local de evento aceita somente Brasil',
);
$check(
  str_contains($osmWizard, 'AccountAddressForm::apply(')
    && str_contains($osmWizard, 'AccountAddressForm::LIBRARY')
    && str_contains($osmWizard, "'#type' => 'address'")
    && str_contains($osmWizard, 'reviewedTags('),
  'revisão OSM usa o widget Address central, CEP Portal e adaptador de tags',
);
$check(
  (bool) preg_match("/available_countries:\\s*\\n\\s+BR: BR\\s*\\n/", $fieldConfig),
  'config: field_address do authenticated aceita só BR',
);
foreach (['administrator', 'associado', 'email_confirmed', 'gerente_loja', 'lms_teacher'] as $bundle) {
  $bundleField = $read("config/sync/field.field.profile.$bundle.field_address.yml");
  $check(
    (bool) preg_match("/available_countries:\\s*\\n\\s+BR: BR\\s*\\n/", $bundleField),
    "config: field_address do perfil $bundle aceita exclusivamente BR",
  );
}

// Loja oculta billing_countries para não permitir seleção de países.
$storeFormDisplay = $read('config/sync/core.entity_form_display.commerce_store.online.default.yml');
$check(
  str_contains($storeFormDisplay, 'billing_countries: true') && !str_contains($storeFormDisplay, "billing_countries:\n    type: options_select"),
  'config: commerce_store oculta billing_countries',
);

// Validação no servidor em AccountAddressForm e normalização em buildStreetNumber.
$check(
  str_contains($form, 'validateCountryCode') && str_contains($form, 'validateAddressElement') && str_contains($form, "country_code'] = 'BR'"),
  'AccountAddressForm: validação e confirmação no servidor de country_code = BR',
);
$check(
  str_contains($form, 'finishCountryElement')
    && str_contains($accountAddressHooks, "types['address_country']")
    && str_contains($accountAddressHooks, 'system_regional_settings'),
  'AccountAddressHooks: intercepta address_country e fixa regional settings em BR',
);
$check(
  str_contains($form, "['postal_code']['#weight'] = -50") && str_contains($form, "['address_line1']['#weight'] = -40"),
  'AccountAddressForm: CEP priorizado no servidor como primeiro campo visível',
);

// Formulário institucional (block_content.aculta_institution):
// O CEP (field_postal_code) deve anteceder rigorosamente todos os campos de endereço visíveis,
// inclusive o campo geral textual de endereço (field_address). Um campo antes do CEP faz o gate falhar.
$institutionFormYaml = $read('config/sync/core.entity_form_display.block_content.aculta_institution.default.yml');
$instData = class_exists(\Symfony\Component\Yaml\Yaml::class) ? \Symfony\Component\Yaml\Yaml::parse($institutionFormYaml) : [];
$instContent = $instData['content'] ?? [];
$postalWeight = $instContent['field_postal_code']['weight'] ?? NULL;
$institutionOrderOk = ($postalWeight !== NULL);
$failedAddressFields = [];
foreach (['field_address', 'field_street_address', 'field_locality', 'field_address_region', 'field_country'] as $addrField) {
  if (isset($instContent[$addrField]['weight']) && (float) $instContent[$addrField]['weight'] <= (float) $postalWeight) {
    $institutionOrderOk = FALSE;
    $failedAddressFields[] = "$addrField (peso {$instContent[$addrField]['weight']} <= {$postalWeight})";
  }
}
$check(
  $institutionOrderOk,
  'config: formulário institucional coloca field_postal_code antes de todos os campos de endereço (incluindo field_address)'
    . ($failedAddressFields ? ': ' . implode(', ', $failedAddressFields) : ''),
);

// Separação estrita entre ordem de formulário (CEP primeiro) e ordem de exibição (convencional: CEP ao final).
$locationCatalogue = $read('web/modules/custom/aculta_portal/modules/aculta_events/src/Catalogue/LocationCatalogue.php');
$modalWidget = $read('web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php');
$institutionViewDisplay = $read('config/sync/core.entity_view_display.block_content.aculta_institution.default.yml');
$partesPos = strpos($locationCatalogue, '$partes = array_filter([');
$catAddressPos = $partesPos !== FALSE ? strpos($locationCatalogue, 'address_line1', $partesPos) : FALSE;
$catCepPos = $partesPos !== FALSE ? strpos($locationCatalogue, 'cepFormatado', $partesPos) : FALSE;
$catalogueOrderOk = ($partesPos !== FALSE && $catAddressPos !== FALSE && $catCepPos !== FALSE && $catAddressPos < $catCepPos);

$widgetPartsPos = strpos($modalWidget, '$parts = array_filter([');
$widgetAddressPos = $widgetPartsPos !== FALSE ? strpos($modalWidget, 'address_line1', $widgetPartsPos) : FALSE;
$widgetPostalPos = $widgetPartsPos !== FALSE ? strpos($modalWidget, 'postal_code', $widgetPartsPos) : FALSE;
$widgetOrderOk = ($widgetPartsPos !== FALSE && $widgetAddressPos !== FALSE && $widgetPostalPos !== FALSE && $widgetAddressPos < $widgetPostalPos);

$institutionViewOk = (bool) preg_match('/hidden:\n(?:.*\n)*?  field_postal_code: true\n/', $institutionViewDisplay);

$check(
  $catalogueOrderOk && $widgetOrderOk && $institutionViewOk,
  'separação estrita: formulários têm CEP primeiro, mas exibição preserva ordem convencional (CEP ao final)',
);

// ADR-034 e instrução de agentes presentes.
$agentsMd = $read('AGENTS.md');
$check(
  is_file($root . '/docs/decisions/ADR-034-pais-unico-brasil-endereco-central.md')
    && str_contains($agentsMd, 'Endereço centralizado em `aculta_portal_cep` e país exclusivo Brasil (BR)'),
  'ADR-034 e instrução global de agentes vigentes',
);

// Perfis de equipe usam address_default e mantêm field_address visível.
foreach (['administrator', 'associado', 'gerente_loja', 'lms_teacher'] as $team) {
  $display = $read("config/sync/core.entity_form_display.profile.$team.default.yml");
  $check(
    (bool) preg_match('/content:\n(?:.*\n)*?  field_address:\n    type: address_default\n/', $display) && !preg_match('/hidden:\n(?:.*\n)*?  field_address: true\n/', $display),
    "config: perfil $team exibe field_address com address_default",
  );
}

// 3. O cliente ViaCEP do contrib não é carregado; o Portal envia o cabeçalho
// exigido pelo endpoint próprio.
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

// 4. O CEP resolvido sobrevive ao AJAX; trocar o CEP substitui o endereço todo.
$check(str_contains($cep, 'const resolvedCep = new Map()'), 'cep-address.js: CEP resolvido guardado por campo');
$check(str_contains($cep, 'isReplacement'), 'cep-address.js: troca de CEP substitui o endereço');
$check(str_contains($cep, 'pendingFill.delete(prefix)'), 'cep-address.js: preenchimento pendente vale só para o próprio rebuild');

// 4b. Campos preenchidos pelo CEP continuam editáveis (permitindo cidades com CEP geral e ajustes).
$check(
  !str_contains($cep, 'readOnly =') && !str_contains($cep, 'field.disabled = true') && str_contains($cep, 'applyFieldValues'),
  'cep-address.js: campos continuam editáveis após autopreenchimento do CEP',
);

// 5. Alias `address` é computado e lê field_address, pois o contrib do Mercado
// Pago o consome. Campo armazenado criaria dados duplicados.
$alias = $read('web/modules/custom/aculta_portal/src/Hook/ProfileAddressAliasHooks.php');
$aliasList = $read('web/modules/custom/aculta_portal/src/Commerce/MercadoPago/BillingAddressAliasItemList.php');
$check(str_contains($alias, "setComputed(TRUE)") && str_contains($alias, "BillingAddressAliasItemList::class"), 'alias address é campo computado (sem armazenamento)');
$check(str_contains($aliasList, "ComputedItemListTrait") && str_contains($aliasList, "'field_address'"), 'alias address lê o field_address do mesmo perfil');

// 5b. A base local de CEPs é consultada antes das APIs externas e não entra no
// Git. A consulta compartilhada mora em CepLookupService.
$lookupService = $read('web/modules/custom/aculta_portal/modules/aculta_portal_cep/src/Lookup/CepLookupService.php');
$check(
  str_contains($lookupService, 'aculta_portal_cep.local_store') && strpos($lookupService, '->local->lookup(') < strpos($lookupService, 'fromViaCep('),
  'CepLookupService: base local consultada antes do ViaCEP/BrasilAPI',
);
$check(
  str_contains($controller, '->lookupService->lookup(') && !str_contains($controller, 'fromViaCep('),
  'CepLookupController: delega à consulta compartilhada, sem duplicar ViaCEP',
);

// 6. Teste de navegador presente (a barreira só vale se o teste existir).
$check(is_file($root . '/scripts/test-account-address-browser.py'), 'teste de navegador do endereço presente');

echo $failures === 0 ? 'ACCOUNT ADDRESS GATE: PASS' . PHP_EOL : "ACCOUNT ADDRESS GATE: FAIL ({$failures})" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
