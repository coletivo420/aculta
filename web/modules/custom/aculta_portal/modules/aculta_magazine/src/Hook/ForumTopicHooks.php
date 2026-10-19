<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\aculta_magazine\Forum\ForumTopicSync;
use Drupal\aculta_magazine\Revista\RevistaPublications;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maintains legacy Magazine topics until the shared discussion field is installed.
 */
final class ForumTopicHooks {

  public function __construct(
    #[Autowire(service: 'aculta_magazine.forum_topic_sync')]
    private readonly ForumTopicSync $sync,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for node entities.
   */
  #[Hook('node_insert')]
  public function nodeInsert(EntityInterface $entity): void {
    if ($this->isPublication($entity) && !$entity->hasField('field_aculta_discussion')) {
      // Transitional fallback while the shared Comment field is not yet
      // installed in Config Sync on an environment.
      $this->sync->sync($entity);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node entities.
   */
  #[Hook('node_update')]
  public function nodeUpdate(EntityInterface $entity): void {
    if ($this->isPublication($entity) && !$entity->hasField('field_aculta_discussion')) {
      // Transitional fallback while the shared Comment field is not yet
      // installed in Config Sync on an environment.
      $this->sync->sync($entity);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for node entities.
   */
  #[Hook('node_delete')]
  public function nodeDelete(EntityInterface $entity): void {
    if ($this->isPublication($entity)) {
      $this->sync->unpublish($entity);
    }
  }

  /**
   * Implements hook_comment_links_alter().
   *
   * Nos tópicos do Fórum, o Core repete "Efetue login ou registre-se para postar comentários" em cada comentário
   * e na área do tópico. Fica só a da área do tópico, que é a resposta principal.
   */
  #[Hook('comment_links_alter')]
  public function commentLinksAlter(array &$links, CommentInterface $entity, array &$context): void {
    // A lista de links do comentário fica em $links['comment']['#links'] (ver CommentLazyBuilders::renderLinks).
    if (!isset($links['comment']['#links']['comment-forbidden'])) {
      return;
    }
    $commented = $entity->getCommentedEntity();
    if ($commented instanceof NodeInterface && $commented->bundle() === 'forum') {
      unset($links['comment']['#links']['comment-forbidden']);
    }
  }

  /**
   * Determines whether the entity is a Revista publication.
   */
  private function isPublication(EntityInterface $entity): bool {
    return $entity instanceof NodeInterface && RevistaPublications::isPublication($entity->bundle());
  }

}
