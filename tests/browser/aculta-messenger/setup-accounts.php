<?php

/**
 * Preparação da suíte de navegador do Messenger. Somente no runtime de teste (aculta.toca.net.br).
 *
 * Idempotente: cria as contas de teste que faltam (teste.b e teste.c, com senha aleatória que nunca é mostrada),
 * e recria a conversa em grupo de teste.comum, teste.b e teste.c com uma mensagem. A conversa one-to-one entre
 * as contas é zerada pela própria suíte. Nenhum segredo é impresso.
 *
 * Uso: php vendor/drush/drush/drush.php scr tests/browser/aculta-messenger/setup-accounts.php
 */

use Drupal\private_message\Entity\PrivateMessage;

$storage = \Drupal::entityTypeManager()->getStorage('user');
$accounts = [
  'teste.comum' => ['mail' => 'teste.comum@teste.invalid', 'roles' => ['email_confirmed']],
  'teste.b' => ['mail' => 'teste.b@teste.invalid', 'roles' => ['email_confirmed']],
  'teste.c' => ['mail' => 'teste.c@teste.invalid', 'roles' => ['email_confirmed']],
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

// The group thread: rebuilt on every run, so the menu's "Limpar histórico" starts from a known state.
$service = \Drupal::service('private_message.service');
$threads = \Drupal::entityTypeManager()->getStorage('private_message_thread');
$group = [];
foreach (['teste.comum', 'teste.b', 'teste.c'] as $name) {
  $group[] = $storage->load($uids[$name]);
}
// The mapper finds the thread of exactly these members (an entity query on two values of one multi-value field
// matches nothing).
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
