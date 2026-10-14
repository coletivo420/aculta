<?php

/**
 * Exclui usuários de teste criados por agentes (regra em docs/portal/GUARDRAILS.md).
 *
 * Executar pelo Drush, com Drupal inicializado:
 *   php vendor/drush/drush/drush.php php:script scripts/cleanup-agent-test-users.php          (simulação)
 *   php vendor/drush/drush/drush.php php:script scripts/cleanup-agent-test-users.php -- --apply
 *
 * Critério (os dois precisam valer): nome começa com "agente-teste-" E e-mail termina em "@testuser.invalid".
 * Contas que não cumprem os dois critérios (ex.: teste.comum, autores, administradores) não são tocadas.
 */

use Drupal\user\Entity\User;

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$storage = \Drupal::entityTypeManager()->getStorage('user');

$candidates = [];
foreach ($storage->loadMultiple() as $account) {
  if ($account->id() <= 1) {
    continue;
  }
  $name = (string) $account->getAccountName();
  $mail = (string) $account->getEmail();
  if (str_starts_with($name, 'agente-teste-') && str_ends_with($mail, '@testuser.invalid')) {
    $candidates[] = $account;
  }
}

if ($candidates === []) {
  echo "Nenhum usuário de teste de agente encontrado.\n";
  return;
}

foreach ($candidates as $account) {
  $line = sprintf('uid=%d nome=%s', $account->id(), $account->getAccountName());
  if ($apply) {
    $account->delete();
    echo "excluído: $line\n";
  }
  else {
    echo "seria excluído (simulação): $line\n";
  }
}
echo $apply ? 'Total excluído: ' . count($candidates) . "\n" : 'Simulação: ' . count($candidates) . " usuário(s). Use -- --apply para excluir.\n";
