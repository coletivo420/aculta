<?php

/**
 * @file
 * Gate: existe um único formulário de aceite, e o ledger grava só o aceite único.
 *
 * Decisão do responsável (2026-10-21). Ver docs/portal/ACEITE-UNICO.md.
 *
 * Falha quando:
 * - aparece identificador `aculta_user_agreement_*` fora da lista fechada abaixo;
 * - há classe de formulário com nome de aceite, consentimento ou acordo fora de AcultaUserAgreementAcceptForm;
 * - há checkbox de aceite em formulário fora da lista autorizada;
 * - há gravação no ledger fora dos arquivos autorizados;
 * - documento normativo ou regra no AGENTS.md está ausente.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$check = static function (bool $ok, string $message) use (&$errors): void {
  if (!$ok) {
    $errors[] = $message;
  }
};

// Identificadores de serviço, rota, menu, tabela, coluna e classes do aceite único. Não são tipos de aceite.
const ALLOWED_IDENTIFIERS = [
  'aculta_user_agreement_terms',
  'aculta_user_agreement_accept',
  'aculta_user_agreement_policy',
  'aculta_user_agreement_registration',
  'aculta_user_agreement_ledger',
  'aculta_user_agreement_hooks',
  'aculta_user_agreement_audit',
  'aculta_user_agreement_type',
];

// Único formulário de aceite permitido.
const ALLOWED_ACCEPT_FORMS = ['AcultaUserAgreementAcceptForm.php'];

// Checkbox de aceite do mesmo tipo único: o formulário de aceite e o cadastro (FormHooks, user_register_form), que
// grava `aculta_user_agreement_terms` pelo mesmo callback. É a variante de cadastro, não um segundo aceite.
const ALLOWED_ACCEPT_CHECKBOX_FILES = ['AcultaUserAgreementAcceptForm.php', 'FormHooks.php'];

// Identificadores que existem só no histórico de atualizações (migração legada). Não são usados em código novo.
const LEGACY_IDENTIFIERS = [
  'aculta_user.install' => ['aculta_user_agreement_email_tracking', 'aculta_user_agreement_default'],
];

// Arquivos autorizados a gravar no ledger.
const ALLOWED_RECORDERS = [
  'AcultaUserAgreementLedger.php',
  'AcultaUserAgreementPolicy.php',
  'AcultaUserAgreementRegistration.php',
  'aculta_user.install',
];

$scan = static function (string $dir, array $extensions): iterable {
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), $extensions, TRUE)) {
      yield $file->getPathname();
    }
  }
};

$custom = $root . '/web/modules/custom';
$theme = $root . '/web/themes/custom';
$codeExtensions = ['php', 'yml', 'twig', 'js', 'module', 'install', 'inc'];

// 1. Identificadores de aceite fora da lista.
foreach (array_merge(iterator_to_array($scan($custom, $codeExtensions), FALSE), iterator_to_array($scan($theme, $codeExtensions), FALSE)) as $path) {
  if (preg_match_all('/aculta_user_agreement_[a-z_]+/', (string) file_get_contents($path), $matches)) {
    foreach (array_unique($matches[0]) as $identifier) {
      $legacy = LEGACY_IDENTIFIERS[basename($path)] ?? [];
      $check(in_array($identifier, ALLOWED_IDENTIFIERS, TRUE) || in_array($identifier, $legacy, TRUE), 'Identificador de aceite fora da lista do aceite único: ' . $identifier . ' em ' . substr($path, strlen($root) + 1) . '.');
    }
  }
}

// 2. Formulários com nome de aceite, consentimento ou acordo.
foreach ($scan($custom, ['php']) as $path) {
  $name = basename($path);
  if (preg_match('/(Aceit|Consent|Consentimento|Agreement|Acordo|Rastreamento)[A-Za-z]*Form\.php$/', $name) === 1) {
    $check(in_array($name, ALLOWED_ACCEPT_FORMS, TRUE), 'Novo formulário de aceite não é permitido: ' . substr($path, strlen($root) + 1) . ' (ver docs/portal/ACEITE-UNICO.md).');
  }
}

// 3. Checkbox de aceite fora do formulário autorizado.
foreach ($scan($custom, ['php']) as $path) {
  $source = (string) file_get_contents($path);
  if (str_contains($source, "'#type' => 'checkbox'") && preg_match('/(aceit[oa]|concordo)[^\'"\n]{0,120}(@terms|@privacy|Termos|Pol[ií]tica)/iu', $source) === 1) {
    $check(in_array(basename($path), ALLOWED_ACCEPT_CHECKBOX_FILES, TRUE), 'Checkbox de aceite fora do formulário único: ' . substr($path, strlen($root) + 1) . '.');
  }
}

// 4. Gravação no ledger somente nos arquivos autorizados.
foreach ($scan($custom, ['php']) as $path) {
  $source = (string) file_get_contents($path);
  if (str_contains($source, '->recordAcultaUserAgreement(') && !in_array(basename($path), ALLOWED_RECORDERS, TRUE)) {
    $check(FALSE, 'Gravação no ledger fora dos arquivos autorizados: ' . substr($path, strlen($root) + 1) . '.');
  }
}

// 5. Documento normativo e regra no AGENTS.md.
$check(is_file($root . '/docs/portal/ACEITE-UNICO.md'), 'docs/portal/ACEITE-UNICO.md ausente.');
$check(str_contains((string) file_get_contents($root . '/AGENTS.md'), 'ACEITE-UNICO'), 'AGENTS.md deve referenciar docs/portal/ACEITE-UNICO.md (aceite único).');

if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, '- ' . $error . "\n");
  }
  fwrite(STDERR, 'SINGLE ACCEPTANCE: FAIL (' . count($errors) . " falhas)\n");
  exit(1);
}
echo "SINGLE ACCEPTANCE: PASS (um formulário de aceite; ledger grava só aculta_user_agreement_terms)\n";
