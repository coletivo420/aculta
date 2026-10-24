<?php

/**
 * @file
 * Provisiona e remove a fixture da validação F-GROUP no Runtime de testes.
 */

declare(strict_types=1);

/**
 * @file
 * Provisiona e remove a fixture da validação F-GROUP no Runtime de testes.
 *
 * Uso (no checkout do servidor de testes, com o site já inicializado):
 *   drush php:script tests/runtime/fgroup-provision.php -- provision
 *   drush php:script tests/runtime/fgroup-provision.php -- teardown.
 *
 * O modo provision cria Group type, relação de tópico, role de membro, Group,
 * quatro contas e um tópico, e imprime os IDs em JSON. O teardown remove
 * exatamente a fixture e desliga a frente. Não toca em conteúdo preexistente.
 */

use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\PermissionScopeInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeAccessRebuild;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

// Fixture constants stay local to these drush scripts, not autoloaded classes.
// phpcs:disable DrupalPractice.Constants.GlobalConstant.GlobalConstant

const FGROUP_TYPE = 'forum_associados';
const FGROUP_ROLE = 'forum_associados-membro';
const FGROUP_PREFIX = 'fgroup_teste_';
const FGROUP_TOPIC_TITLE = '[TESTE F-GROUP] Tópico restrito';
const FGROUP_PUBLIC_TOPIC_TITLE = '[TESTE F-GROUP] Tópico público';

$mode = $extra[0] ?? 'provision';
$entity_type_manager = \Drupal::entityTypeManager();
$config_factory = \Drupal::configFactory();

if ($mode === 'teardown') {
  $config_factory->getEditable('aculta_forum.settings')
    ->set('restricted_group_type', '')
    ->save();
  \Drupal::service(NodeAccessRebuild::class)->rebuild();

  $group_storage = $entity_type_manager->getStorage('group');
  foreach ($group_storage->loadByProperties(['type' => FGROUP_TYPE]) as $group) {
    $group->delete();
  }
  $node_storage = $entity_type_manager->getStorage('node');
  $nids = $node_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'aculta_forum_topic')
    ->condition('title', '[TESTE F-GROUP]%', 'LIKE')
    ->execute();
  if ($nids !== []) {
    $node_storage->delete($node_storage->loadMultiple($nids));
  }
  $gallery_storage = $entity_type_manager->getStorage('media_gallery');
  $gallery_ids = $gallery_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('title', '[TESTE F-GROUP]%', 'LIKE')
    ->execute();
  foreach ($gallery_storage->loadMultiple($gallery_ids) as $gallery) {
    $gallery->delete();
  }
  $media_storage = $entity_type_manager->getStorage('media');
  $media_ids = $media_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('name', 'fgroup-%', 'LIKE')
    ->execute();
  foreach ($media_storage->loadMultiple($media_ids) as $media) {
    $media->delete();
  }
  $file_storage = $entity_type_manager->getStorage('file');
  $file_query = $file_storage->getQuery()->accessCheck(FALSE);
  $file_or = $file_query->orConditionGroup()
    ->condition('uri', 'private://fgroup-%', 'LIKE')
    ->condition('uri', 'private://forum/anexos/fgroup-%', 'LIKE');
  $file_ids = $file_query->condition($file_or)->execute();
  foreach ($file_storage->loadMultiple($file_ids) as $file) {
    $file->delete();
  }
  $user_storage = $entity_type_manager->getStorage('user');
  $uids = $user_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('name', FGROUP_PREFIX . '%', 'LIKE')
    ->execute();
  if ($uids !== []) {
    $associado_storage = $entity_type_manager->getStorage('aculta_associado');
    $associado_ids = $associado_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uids, 'IN')
      ->execute();
    if ($associado_ids !== []) {
      $associado_storage->delete($associado_storage->loadMultiple($associado_ids));
    }
    $user_storage->delete($user_storage->loadMultiple($uids));
  }
  $role = GroupRole::load(FGROUP_ROLE);
  if ($role !== NULL) {
    $role->delete();
  }
  $group_type = GroupType::load(FGROUP_TYPE);
  if ($group_type !== NULL) {
    foreach ($group_type->getRoles() as $group_role) {
      if ($group_role->id() === FGROUP_ROLE) {
        $group_role->delete();
      }
    }
    $group_type->delete();
  }

  \Drupal::service('cache_tags.invalidator')->invalidateTags([
    'node_list',
    'group_relationship_list',
    'config:aculta_forum.settings',
  ]);
  drupal_flush_all_caches();
  echo json_encode([
    'mode' => 'teardown',
    'restricted_group_type' => $config_factory->get('aculta_forum.settings')->get('restricted_group_type'),
  ], JSON_PRETTY_PRINT) . PHP_EOL;
  return;
}

// Provisionamento idempotente: se as entidades existirem, reutiliza.
$group_type = GroupType::load(FGROUP_TYPE) ?? GroupType::create([
  'id' => FGROUP_TYPE,
  'label' => 'Fórum de associados (teste)',
  'creator_membership' => FALSE,
  'creator_wizard' => FALSE,
]);
$group_type->save();

$relationship_type_storage = $entity_type_manager->getStorage('group_relationship_type');
$relationship_types = $relationship_type_storage->loadByProperties([
  'group_type' => FGROUP_TYPE,
  'plugin_id' => 'group_node:aculta_forum_topic',
]);
if ($relationship_types === []) {
  $relationship_type_storage->save(
    $relationship_type_storage->createFromPlugin($group_type, 'group_node:aculta_forum_topic'),
  );
}

$role = GroupRole::load(FGROUP_ROLE) ?? GroupRole::create([
  'id' => FGROUP_ROLE,
  'label' => 'Membro (teste)',
  'group_type' => FGROUP_TYPE,
  // Synchronized insider role: every authenticated member inherits the
  // permission, including after membership is recreated by the sync.
  'scope' => PermissionScopeInterface::INSIDER_ID,
  'global_role' => RoleInterface::AUTHENTICATED_ID,
  'permissions' => ['view group_node:aculta_forum_topic entity'],
]);
$role->save();

$groups = $entity_type_manager->getStorage('group')->loadByProperties([
  'type' => FGROUP_TYPE,
  'label' => '[TESTE F-GROUP] Associados',
]);
$group = reset($groups) ?: Group::create([
  'type' => FGROUP_TYPE,
  'label' => '[TESTE F-GROUP] Associados',
  'uid' => 1,
]);
$group->save();

$accounts = [
  'ativo_membro' => ['situacao' => 'ativo', 'member' => TRUE],
  'ativo_sem_membership' => ['situacao' => 'ativo', 'member' => FALSE],
  'suspenso_membro' => ['situacao' => 'suspenso', 'member' => TRUE],
  'comum' => ['situacao' => NULL, 'member' => FALSE],
];
$ids = [];
foreach ($accounts as $suffix => $data) {
  $name = FGROUP_PREFIX . $suffix;
  $user = user_load_by_name($name) ?: User::create([
    'name' => $name,
    'mail' => $name . '@example.invalid',
    'status' => 1,
  ]);
  $user->save();
  $ids['users'][$suffix] = (int) $user->id();
  if ($data['situacao'] !== NULL) {
    \Drupal::service('aculta_user.active_associate')
      ->definir((int) $user->id(), $data['situacao'], 1, 'Fixture F-GROUP');
  }
  if ($data['member'] && $group->getMember($user) === FALSE) {
    $group->addRelationship($user, 'group_membership')->save();
  }
}

$topic_storage = $entity_type_manager->getStorage('node');
$topic_ids = $topic_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'aculta_forum_topic')
  ->condition('title', FGROUP_TOPIC_TITLE)
  ->execute();
$topic = $topic_ids === []
  ? Node::create([
    'type' => 'aculta_forum_topic',
    'title' => FGROUP_TOPIC_TITLE,
    'status' => 1,
    'uid' => 1,
    'field_domain_source' => ['target_id' => 'forum_aculta_org'],
  ])
  : $topic_storage->load(reset($topic_ids));
$topic->save();
$ids['topic'] = (int) $topic->id();

$public_ids = $topic_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'aculta_forum_topic')
  ->condition('title', FGROUP_PUBLIC_TOPIC_TITLE)
  ->execute();
$public_topic = $public_ids === []
  ? Node::create([
    'type' => 'aculta_forum_topic',
    'title' => FGROUP_PUBLIC_TOPIC_TITLE,
    'status' => 1,
    'uid' => 1,
    'field_domain_source' => ['target_id' => 'forum_aculta_org'],
  ])
  : $topic_storage->load(reset($public_ids));
$public_topic->save();
$ids['public_topic'] = (int) $public_topic->id();

$existing = $entity_type_manager->getStorage('group_relationship')->getQuery()
  ->accessCheck(FALSE)
  ->condition('gid', $group->id())
  ->condition('entity_id', $topic->id())
  ->condition('plugin_id', 'group_node:aculta_forum_topic')
  ->execute();
if ($existing === []) {
  $group->addRelationship($topic, 'group_node:aculta_forum_topic')->save();
}

$config_factory->getEditable('aculta_forum.settings')
  ->set('restricted_group_type', FGROUP_TYPE)
  ->save();
\Drupal::service(NodeAccessRebuild::class)->rebuild();
drupal_flush_all_caches();

$ids['group'] = (int) $group->id();
$ids['group_type'] = FGROUP_TYPE;
$ids['realm'] = RestrictedForumAccessHooks::REALM;
$ids['restricted_group_type'] = $config_factory->get('aculta_forum.settings')->get('restricted_group_type');
echo json_encode($ids, JSON_PRETTY_PRINT) . PHP_EOL;
