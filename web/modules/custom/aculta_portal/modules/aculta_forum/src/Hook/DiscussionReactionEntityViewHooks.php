<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\DiscussionReactionBuilder;
use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Attaches the discussion reaction bar and author stats to comments, forum posts, and forum topics.
 */
final class DiscussionReactionEntityViewHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_reaction_builder')]
    private readonly DiscussionReactionBuilder $reactionBuilder,
    #[Autowire(service: 'aculta_forum.discussion_reaction_manager')]
    private readonly DiscussionReactionManager $reactionManager,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view', order: Order::Last)]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      $build['reaction_bar'] = $this->reactionBuilder->build($entity, $this->currentUser) + ['#weight' => 20];
      $this->enrichAuthorStats($build, $entity);
      return;
    }

    if ($entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic') {
      $build['reaction_bar'] = $this->reactionBuilder->build($entity, $this->currentUser) + ['#weight' => 20];
      $this->enrichAuthorStats($build, $entity);
      return;
    }

    if ($entity instanceof CommentInterface &&
      $entity->bundle() === 'aculta_discussion_post' &&
      $entity->getFieldName() === 'field_aculta_discussion') {
      $build['reaction_bar'] = $this->reactionBuilder->build($entity, $this->currentUser) + ['#weight' => 20];
      $this->enrichAuthorStats($build, $entity);
    }
  }

  /**
   * Enriches author card and preprocess variables for comment templates.
   */
  #[Hook('preprocess_comment')]
  public function preprocessComment(array &$variables): void {
    $comment = $variables['elements']['#comment'] ?? NULL;
    if (!$comment instanceof CommentInterface
      || $comment->bundle() !== 'aculta_discussion_post'
      || $comment->getFieldName() !== 'field_aculta_discussion') {
      return;
    }

    $uid = (int) $comment->getOwnerId();
    if ($uid > 0) {
      $stats = $this->reactionManager->getUserStats($uid);
      $postsCount = $this->reactionManager->getAuthorPublicPostCount($uid);
      $variables['author_posts'] = $postsCount;
      $variables['author_reactions_received'] = $stats['reactions_received'];
      $variables['author_reactions_given'] = $stats['reactions_given'];
      if (isset($variables['content']['author_card']) && is_array($variables['content']['author_card'])) {
        $variables['content']['author_card']['#reactions_received'] = $stats['reactions_received'];
        $variables['content']['author_card']['#reactions_given'] = $stats['reactions_given'];
      }
    }

    if (!isset($variables['content']['reaction_bar'])) {
      $variables['content']['reaction_bar'] = $this->reactionBuilder->build($comment, $this->currentUser) + ['#weight' => 20];
    }
    $variables['reaction_bar'] = $variables['content']['reaction_bar'];
  }

  /**
   * Enriches author stats and reaction bar for forum topic node templates.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? ($variables['elements']['#node'] ?? NULL);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'aculta_forum_topic') {
      return;
    }

    $uid = (int) $node->getOwnerId();
    if ($uid > 0) {
      $stats = $this->reactionManager->getUserStats($uid);
      $postsCount = $this->reactionManager->getAuthorPublicPostCount($uid);
      $variables['author_posts'] = $postsCount;
      $variables['author_reactions_received'] = $stats['reactions_received'];
      $variables['author_reactions_given'] = $stats['reactions_given'];
      if (isset($variables['content']['author_card']) && is_array($variables['content']['author_card'])) {
        $variables['content']['author_card']['#reactions_received'] = $stats['reactions_received'];
        $variables['content']['author_card']['#reactions_given'] = $stats['reactions_given'];
      }
    }

    if (!isset($variables['content']['reaction_bar'])) {
      $variables['content']['reaction_bar'] = $this->reactionBuilder->build($node, $this->currentUser) + ['#weight' => 20];
    }
    $variables['reaction_bar'] = $variables['content']['reaction_bar'];
  }

  /**
   * Attaches author reaction and post stats to the entity build array.
   */
  private function enrichAuthorStats(array &$build, EntityInterface $entity): void {
    $uid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;
    if ($uid <= 0) {
      return;
    }

    $stats = $this->reactionManager->getUserStats($uid);
    $postsCount = $this->reactionManager->getAuthorPublicPostCount($uid);
    $build['#author_posts'] = $postsCount;
    $build['#author_reactions_received'] = $stats['reactions_received'];
    $build['#author_reactions_given'] = $stats['reactions_given'];
    $build['#cache']['tags'][] = 'aculta_user_reactions:' . $uid;

    if (isset($build['author_card']) && is_array($build['author_card'])) {
      $build['author_card']['#reactions_received'] = $stats['reactions_received'];
      $build['author_card']['#reactions_given'] = $stats['reactions_given'];
      $build['author_card']['#cache']['tags'][] = 'aculta_user_reactions:' . $uid;
    }
  }

}
