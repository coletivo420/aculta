<?php

/**
 * @file
 * Mede a atualização do índice na transição público → restrito.
 *
 * Remove a relação de Group do tópico restrito (torna público), indexa,
 * recria a relação e mede o índice antes e depois da reindexação. Executar
 * com a fixture F-GROUP provisionada; o teardown remove tudo.
 */

declare(strict_types=1);

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\search_api\Entity\Index;

// Fixture constants stay local to these drush scripts, not autoloaded classes.
// phpcs:disable DrupalPractice.Constants.GlobalConstant.GlobalConstant

const FGROUP_PREFIX = 'fgroup_teste_';

$entity_type_manager = \Drupal::entityTypeManager();
$index = Index::load('aculta_forum');
$purpose = \Drupal::service('aculta_portal.domain_purpose');
$topic_ids = $entity_type_manager->getStorage('node')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'aculta_forum_topic')
  ->condition('title', '[TESTE F-GROUP] Tópico restrito')
  ->execute();
$topic = $entity_type_manager->getStorage('node')->load(reset($topic_ids));
$groups = $entity_type_manager->getStorage('group')->loadByProperties(['type' => 'forum_associados']);
$group = reset($groups);
$users = $entity_type_manager->getStorage('user')->loadByProperties(['name' => FGROUP_PREFIX . 'ativo_membro']);
$member = reset($users);

$accounts = ['anonimo' => new AnonymousUserSession(), 'ativo_membro' => $member];
$visible = static function () use ($index, $purpose, $accounts): array {
  $result = [];
  $purpose->runInPurpose('forum', static function () use ($index, $accounts, &$result): void {
    foreach ($accounts as $label => $account) {
      $query = $index->query();
      $query->keys('fgroup');
      $query->setOption('search_api_access_account', $account);
      $query->range(0, 10);
      $query_result = $query->execute();
      $result[$label] = [
        'total' => (int) $query_result->getResultCount(),
        'ids' => array_keys($query_result->getResultItems()),
      ];
    }
  });
  return $result;
};

$out = [
  'topic' => (int) $topic->id(),
  'group' => (int) $group->id(),
  'initial' => [],
  'public_before_reindex' => [],
  'restricted_before_reindex' => [],
  'remaining_after_restrict' => [],
  'restricted_after_reindex' => [],
];

$index->indexItems(100);
$out['initial'] = $visible();

$relationship_storage = $entity_type_manager->getStorage('group_relationship');
$relationship_ids = $relationship_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('gid', $group->id())
  ->condition('entity_id', $topic->id())
  ->condition('plugin_id', 'group_node:aculta_forum_topic')
  ->execute();
$relationship_storage->delete($relationship_storage->loadMultiple($relationship_ids));
$index->indexItems(100);
$out['public_before_reindex'] = $visible();

$group->addRelationship($topic, 'group_node:aculta_forum_topic')->save();
$out['restricted_before_reindex'] = $visible();
$out['remaining_after_restrict'] = $index->getTrackerInstance()->getRemainingItems();
$index->indexItems(100);
$out['restricted_after_reindex'] = $visible();

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
