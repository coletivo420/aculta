<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\RestrictedForum;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeAccessControlHandlerInterface;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Drupal\node\NodeInterface;
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
