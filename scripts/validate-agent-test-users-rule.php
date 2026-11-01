<?php

/**
 * Gate: regra de usuários de teste de agentes existe e está operacional (docs/portal/GUARDRAILS.md).
 *
 * Verifica no repositório (sem Runtime): a seção da regra, o prefixo e o domínio exigidos, a exclusão de
 * contas permanentes citadas na regra, e a existência do script de limpeza com o critério duplo.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$guardrails = $root . '/docs/portal/GUARDRAILS.md';
$text = is_file($guardrails) ? file_get_contents($guardrails) : '';
if ($text === '') {
  $errors[] = 'docs/portal/GUARDRAILS.md ausente';
}
else {
  foreach ([
    '## Usuários de teste criados por agentes',
    'excluído ao fim dos testes',
    'prefixo `agente-teste-`',
    '@testuser.invalid',
    'teste.comum',
    'scripts/cleanup-agent-test-users.php',
    'scripts/validate-agent-test-users-rule.php',
  ] as $needle) {
    if (!str_contains($text, $needle)) {
      $errors[] = "GUARDRAILS.md sem '$needle'";
    }
  }
}

$cleanup = $root . '/scripts/cleanup-agent-test-users.php';
if (!is_file($cleanup)) {
  $errors[] = 'scripts/cleanup-agent-test-users.php ausente';
}
else {
  $code = file_get_contents($cleanup);
  foreach (["str_starts_with(\$name, 'agente-teste-')", "str_ends_with(\$mail, '@testuser.invalid')", '$account->id() <= 1', "'--apply'"] as $needle) {
    if (!str_contains($code, $needle)) {
      $errors[] = "cleanup sem o critério '$needle'";
    }
  }
}

if ($errors !== []) {
  echo "AGENT TEST USERS RULE GATE: FAIL\n";
  foreach ($errors as $e) {
    echo " - $e\n";
  }
  exit(1);
}
echo "AGENT TEST USERS RULE GATE: PASS (regra em GUARDRAILS.md; limpeza com critério duplo; contas permanentes preservadas)\n";
