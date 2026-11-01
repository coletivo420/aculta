<?php

/**
 * @file
 * Preparação da suíte de navegador do Messenger no Runtime de teste.
 *
 * Idempotente: cria as contas de teste que faltam (senhas não são mostradas) e
 * recria a conversa fixture teste.comum/teste.b/teste.c. Remove somente a
 * thread teste.b/teste.c/teste.d para que a suíte a crie pela interface. A
 * conversa um-a-um teste.b/teste.c é zerada pela suíte.
 * Nenhum segredo é impresso.
 *
 * Use: php vendor/drush/drush/drush.php scr
 * tests/browser/aculta-messenger/setup-accounts.php
 */

use Drupal\Core\Database\Database;
use Drupal\private_message\Entity\PrivateMessage;

// This script creates accounts and messages. Check the database before writes.
$database = Database::getConnection()->getConnectionOptions();
if (($database['database'] ?? '') !== 'aculta_runtime') {
  throw new \RuntimeException('Refused: the messenger suite prepares only the test runtime database (aculta_runtime).');
}

$storage = \Drupal::entityTypeManager()->getStorage('user');
$accounts = [
  'teste.comum' => ['mail' => 'teste.comum@teste.invalid', 'roles' => ['email_confirmed']],
  'teste.b' => ['mail' => 'teste.b@teste.invalid', 'roles' => ['email_confirmed']],
  'teste.c' => ['mail' => 'teste.c@teste.invalid', 'roles' => ['email_confirmed']],
  'teste.d' => ['mail' => 'teste.d@teste.invalid', 'roles' => ['email_confirmed']],
];

$uids = [];
foreach ($accounts as $name => $spec) {
  $found = $storage->loadByProperties(['name' => $name]);
  if ($found) {
    $user = reset($found);
  }
  else {
    $user = $storage->create([
      'name' => $name,
      'mail' => $spec['mail'],
      'status' => 1,
      'pass' => \Drupal::service('password_generator')->generate(32),
    ]);
    foreach ($spec['roles'] as $role) {
      $user->addRole($role);
    }
    $user->save();
    echo "created $name\n";
  }
  $uids[$name] = (int) $user->id();
}

$service = \Drupal::service('private_message.service');
$threads = \Drupal::entityTypeManager()->getStorage('private_message_thread');

// Reset only the UI-created group; the fixture group stays independent.
$uiGroup = [];
foreach (['teste.b', 'teste.c', 'teste.d'] as $name) {
  $uiGroup[] = $storage->load($uids[$name]);
}
$uiGroupId = \Drupal::service('private_message.mapper')->getThreadIdForMembers($uiGroup);
if ($uiGroupId) {
  $old = $threads->load($uiGroupId);
  $messages = $old->get('private_messages')->referencedEntities();
  $old->delete();
  \Drupal::entityTypeManager()->getStorage('private_message')->delete($messages);
}

// Rebuild the fixture group so "Limpar histórico" starts from a known state.
$group = [];
foreach (['teste.comum', 'teste.b', 'teste.c'] as $name) {
  $group[] = $storage->load($uids[$name]);
}
// The mapper finds the exact member set; entity queries on a multivalue field
// cannot express this with one condition per UID.
$existing = \Drupal::service('private_message.mapper')->getThreadIdForMembers($group);
if ($existing) {
  $old = $threads->load($existing);
  $messages = $old->get('private_messages')->referencedEntities();
  $old->delete();
  \Drupal::entityTypeManager()->getStorage('private_message')->delete($messages);
}
$thread = $service->getThreadForMembers($group);
$message = PrivateMessage::create([
  'owner' => $uids['teste.comum'],
  'message' => ['value' => 'Mensagem de grupo de teste.', 'format' => 'plain_text'],
]);
$message->save();
$thread->addMessage($message);
$thread->save();
echo 'group thread ' . $thread->id() . ' members ' . count($thread->getMembers()) . "\n";
echo 'uids ' . json_encode($uids) . "\n";
