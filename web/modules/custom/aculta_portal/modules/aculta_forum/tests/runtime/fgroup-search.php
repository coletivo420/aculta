<?php

/**
 * @file
 * Mede a busca do Fórum restrito por conta no Runtime de testes.
 *
 * Executar entre o provision e o teardown da fixture F-GROUP. Indexa o
 * `aculta_forum` e consulta com o purpose `forum`, que é o contexto do host
 * público. Não cria conteúdo.
 */

declare(strict_types=1);

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\search_api\Entity\Index;

const FGROUP_PREFIX = 'fgroup_teste_';

$index = Index::load('aculta_forum');
if ($index === NULL) {
  echo json_encode(['error' => 'índice aculta_forum ausente']) . PHP_EOL;
  return;
}

$entity_type_manager = \Drupal::entityTypeManager();
$accounts = ['anonimo' => new AnonymousUserSession()];
foreach (['ativo_membro', 'ativo_sem_membership', 'suspenso_membro', 'comum'] as $suffix) {
  $users = $entity_type_manager->getStorage('user')->loadByProperties([
    'name' => FGROUP_PREFIX . $suffix,
  ]);
  $accounts[$suffix] = reset($users);
}

$result = [
  'indexed_now' => $index->indexItems(100),
  'indexed_total' => $index->getTrackerInstance()->getIndexedItemsCount(),
  'accounts' => [],
];

\Drupal::service('aculta_portal.domain_purpose')->runInPurpose(
  'forum',
  function () use ($index, $accounts, &$result): void {
    foreach ($accounts as $label => $account) {
      $query = $index->query();
      $query->keys('fgroup');
      $query->setOption('search_api_access_account', $account);
      // One item per page proves the total is filtered before paging.
      $query->range(0, 1);
      $query_result = $query->execute();
      $result['accounts'][$label] = [
        'total' => (int) $query_result->getResultCount(),
        'page' => array_keys($query_result->getResultItems()),
      ];
    }
  },
);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
