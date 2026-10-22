<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Wiki;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Classifica a gravação de um verbete da Wiki em métrica de publicação.
 *
 * A Wiki publica pelo fluxo editorial `wiki_editorial`: rascunho e revisão não
 * publicam, e a publicação grava uma revisão nova. Daí:
 * - `wiki.entries.published` conta a primeira publicação de um verbete;
 * - `wiki.entries.updated` conta a publicação de uma revisão nova de um
 *   verbete que já tinha revisão publicada;
 * - gravação que não cria revisão nova não conta, porque não houve publicação;
 * - conteúdo sincronizado ou importado (`isSyncing()`) não conta.
 *
 * Este módulo não importa classes de `aculta_wiki`: a classificação depende só
 * do tipo de conteúdo `wiki_entry` e do estado editorial.
 */
final class WikiProvider {

  /**
   * Métrica da primeira publicação de um verbete.
   */
  public const PUBLISHED = 'wiki.entries.published';

  /**
   * Métrica de revisão publicada de um verbete já publicado.
   */
  public const UPDATED = 'wiki.entries.updated';

  /**
   * Tipo de conteúdo dos verbetes.
   */
  public const BUNDLE = 'wiki_entry';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Métrica da criação de um verbete, ou NULL quando não há publicação.
   */
  public function metricForInsert(NodeInterface $node): ?string {
    return $this->metricForPublication($node);
  }

  /**
   * Métrica da atualização de um verbete, ou NULL quando não há publicação.
   */
  public function metricForUpdate(NodeInterface $node): ?string {
    if (!$this->createdRevision($node)) {
      return NULL;
    }
    return $this->metricForPublication($node);
  }

  /**
   * Métrica de uma gravação publicada, ou NULL quando não é publicação.
   */
  private function metricForPublication(NodeInterface $node): ?string {
    if ($node->bundle() !== self::BUNDLE || $node->isSyncing() || !$node->isPublished()) {
      return NULL;
    }
    return $this->hasPublishedRevision($node) ? self::UPDATED : self::PUBLISHED;
  }

  /**
   * Indica se a gravação criou uma revisão diferente da original.
   */
  private function createdRevision(NodeInterface $node): bool {
    $revision_id = $node->getRevisionId();
    $original = $node->getOriginal();
    return $revision_id !== NULL && $original !== NULL && $revision_id !== $original->getRevisionId();
  }

  /**
   * Indica se o verbete já tinha outra revisão publicada.
   */
  private function hasPublishedRevision(NodeInterface $node): bool {
    if (!$node->getEntityType()->isRevisionable() || $node->getRevisionId() === NULL) {
      return FALSE;
    }

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('nid', $node->id())
      ->condition('status', 1)
      ->condition('vid', $node->getRevisionId(), '<>');

    return (bool) $query->execute();
  }

}
