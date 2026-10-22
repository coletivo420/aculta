<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\aculta_wiki\Notifier\WikiReviewNotifier;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
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
  ) {}

  /**
   * Compara o estado editorial antes e depois da atualização do verbete.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'wiki_entry') {
      return;
    }
    if ($this->moderationState($entity) !== self::STATE_REVIEW) {
      return;
    }
    $original = $entity->getOriginal();
    if ($original instanceof NodeInterface && $this->moderationState($original) === self::STATE_REVIEW) {
      return;
    }
    $this->notifier->notifyReview($entity);
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
