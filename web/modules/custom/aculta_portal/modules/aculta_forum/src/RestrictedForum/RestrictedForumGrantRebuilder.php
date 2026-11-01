<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\RestrictedForum;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeAccessControlHandlerInterface;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\Utility;
use Psr\Log\LoggerInterface;

/**
 * Regrava os grants de leitura do Fórum restrito sem recarregar o site todo.
 *
 * O Core grava grants quando um nó é salvo, mas a relação de Group pode
 * mudar sem salvar o tópico. Esta classe refaz `acquireGrants()` (que já
 * inclui os grants dos outros módulos) e grava o resultado para o nó afetado.
 *
 * Falha aqui não abre acesso: a política de `entity_access` revalida Group e
 * associação a cada requisição. O efeito da falha é apenas listagem
 * desatualizada, registrada no log para reconciliação.
 */
final class RestrictedForumGrantRebuilder {

  /**
   * Campo de categorias do tópico. */
  private const CATEGORY_FIELD = 'field_aculta_categories';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly NodeGrantDatabaseStorageInterface $grantStorage,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Regrava os grants de um tópico.
   */
  public function rebuildForTopic(NodeInterface $topic): bool {
    if ($topic->bundle() !== 'aculta_forum_topic' || $topic->id() === NULL) {
      return FALSE;
    }
    try {
      $handler = $this->entityTypeManager->getAccessControlHandler('node');
      if (!$handler instanceof NodeAccessControlHandlerInterface) {
        $this->logger->error('Handler de acesso do nó indisponível; grants do tópico @nid não foram regravados.', ['@nid' => (string) $topic->id()]);
        return FALSE;
      }
      // Sem realm: acquireGrants() reúne os grants de todos os módulos e o
      // padrão público do Core, então a gravação reproduz o estado completo.
      $this->grantStorage->write($topic, $handler->acquireGrants($topic));
      $this->cacheTagsInvalidator->invalidateTags([
        'node:' . (int) $topic->id(),
        'node_list',
      ]);
      $this->trackSearchReindex($topic);
      return TRUE;
    }
    catch (\Throwable $e) {
      $this->logger->error('Falha ao regravar grants do tópico @nid: @message', [
        '@nid' => (string) $topic->id(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Marca o tópico e seus posts para reindexação no Search API.
   *
   * O índice grava os grants do tópico no momento da indexação. Mudar a
   * relação de Group não salva o nó, então sem este tracking a busca ficaria
   * com o grant antigo (por exemplo, all:0) até uma reindexação manual.
   */
  private function trackSearchReindex(NodeInterface $topic): void {
    if (!$this->entityTypeManager->hasDefinition('search_api_index')) {
      return;
    }
    try {
      $index = $this->entityTypeManager->getStorage('search_api_index')->load('aculta_forum');
      if (!$index instanceof IndexInterface || !$index->status()) {
        return;
      }
      $node_datasource = $index->getDatasource('entity:node');
      $topic_item_id = $node_datasource?->getItemId($topic->getTypedData());
      if (!is_string($topic_item_id) || $topic_item_id === '') {
        return;
      }
      $post_storage = $this->entityTypeManager->getStorage('aculta_forum_post');
      $post_ids = array_map('intval', $post_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('topic.target_id', $topic->id())
        ->execute());
      $post_datasource = $index->getDatasource('entity:aculta_forum_post');
      $post_item_ids = [];
      foreach ($post_storage->loadMultiple($post_ids) as $post) {
        $post_item_id = $post_datasource?->getItemId($post->getTypedData());
        if (is_string($post_item_id) && $post_item_id !== '') {
          $post_item_ids[] = $post_item_id;
        }
      }
      // Remove o item imediatamente e o marca para reindexação: enquanto a
      // indexação não roda, o resultado fica ausente (fail closed) em vez de
      // manter o grant antigo, que poderia expor um tópico recém-restrito.
      $index->trackItemsInserted('entity:node', [$topic_item_id]);
      $index->getServerInstance()?->deleteItems($index, [
        Utility::createCombinedId('entity:node', $topic_item_id),
      ]);
      if ($post_item_ids !== []) {
        $index->trackItemsInserted('entity:aculta_forum_post', $post_item_ids);
        $index->getServerInstance()?->deleteItems($index, array_map(
          static fn (string $item_id): string => Utility::createCombinedId('entity:aculta_forum_post', $item_id),
          $post_item_ids,
        ));
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Falha ao marcar tópico @nid para reindexação: @message', [
        '@nid' => (string) $topic->id(),
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Regrava os grants de todos os tópicos que usam uma categoria.
   *
   * @return int
   *   Quantidade de tópicos regravados com sucesso.
   */
  public function rebuildForCategory(int $termId): int {
    if ($termId <= 0) {
      return 0;
    }
    try {
      $nids = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(FALSE)
        ->condition('type', 'aculta_forum_topic')
        ->condition(self::CATEGORY_FIELD, $termId)
        ->execute();
    }
    catch (\Throwable $e) {
      $this->logger->error('Falha ao localizar tópicos da categoria @tid: @message', [
        '@tid' => (string) $termId,
        '@message' => $e->getMessage(),
      ]);
      return 0;
    }
    if ($nids === []) {
      return 0;
    }
    $count = 0;
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      if ($this->rebuildForTopic($node)) {
        $count++;
      }
    }
    return $count;
  }

}
