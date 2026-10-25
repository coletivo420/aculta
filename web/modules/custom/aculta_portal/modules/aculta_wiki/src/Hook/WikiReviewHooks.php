<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\aculta_wiki\Notifier\WikiReviewNotifier;
use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\Service\WikiEditorialEventQueue;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\node\NodeInterface;

/**
 * Dispara o aviso de revisão quando o estado editorial vira "review".
 *
 * O estado anterior vem de `getOriginal()`: o hook não olha o histórico de
 * revisões e só age na transição real. Salvar um verbete que já estava em
 * revisão não gera aviso novo.
 */
final class WikiReviewHooks {

  /**
   * Estado editorial que dispara o aviso.
   */
  public const STATE_REVIEW = 'review';

  public function __construct(
    private readonly WikiReviewNotifier $notifier,
    private readonly WikiEditorialEventQueue $events,
    private readonly ?ModuleHandlerInterface $moduleHandler = NULL,
  ) {}

  /**
 * Captures a directly created entry that is already in review. */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'wiki_entry'
      || ($entity instanceof SynchronizableInterface && $entity->isSyncing())
      || !$entity->isDefaultRevision()
      || $this->moderationState($entity) !== self::STATE_REVIEW) {
      return;
    }
    $this->enqueue($entity);
  }

  /**
   * Compara o estado editorial antes e depois da atualização do verbete.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'wiki_entry') {
      return;
    }
    if ($entity instanceof SynchronizableInterface && $entity->isSyncing()) {
      return;
    }
    if (!$entity->isDefaultRevision()) {
      return;
    }
    if ($this->moderationState($entity) !== self::STATE_REVIEW) {
      return;
    }
    $original = $entity->getOriginal();
    if ($original instanceof NodeInterface && $this->moderationState($original) === self::STATE_REVIEW) {
      return;
    }
    $this->enqueue($entity);
  }

  /**
   * Queues the current persisted revision and preserves the existing email. */
  private function enqueue(NodeInterface $entity): void {
    $revisionId = $entity->getRevisionId();
    if ($revisionId === NULL || (int) $revisionId < 1 || !$entity->uuid()) {
      return;
    }
    $this->events->enqueue(new WikiEntryReviewRequestedEvent($entity->uuid(), (int) $revisionId));
    if ($this->moduleHandler === NULL || !$this->moduleHandler->moduleExists('aculta_wiki_messenger')) {
      $this->notifier->notifyReview($entity);
    }
  }

  /**
   * Estado editorial da revisão atual, ou string vazia quando não houver.
   */
  private function moderationState(NodeInterface $node): string {
    if (!$node->hasField('moderation_state') || $node->get('moderation_state')->isEmpty()) {
      return '';
    }
    return (string) $node->get('moderation_state')->value;
  }

}
