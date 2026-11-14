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
    #[Autowire(service: 'entity_type.manager')]
    private readonly \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager,
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
    // Nível real da resposta: o Drupal grava um ponto por nível na coluna thread.
    $threadComment = $variables['comment'] ?? NULL;
    if ($threadComment instanceof \Drupal\comment\CommentInterface) {
      $variables['reply_depth'] = min(3, substr_count((string) $threadComment->get('thread')->value, '.'));
      // Resposta em nível: lado oposto ao cartão ao qual responde (pai). A raiz segue a alternância da conversa.
      $variables['accent_side'] = $this->discussionSide($threadComment);
      // Primeira resposta entre irmãos do mesmo pai (mostra a seta de resposta).
      if ($variables['reply_depth'] >= 1) {
        $parentId = (int) ($threadComment->get('pid')->target_id ?? 0);
        $siblings = $this->entityTypeManager->getStorage('comment')->getQuery()
          ->accessCheck(FALSE)
          ->condition('pid', $parentId)
          ->condition('entity_id', $threadComment->getCommentedEntityId())
          ->condition('field_name', 'field_aculta_discussion')
          ->condition('status', 1)
          ->sort('thread', 'ASC')
          ->execute();
        $position = array_search((int) $threadComment->id(), array_values(array_map('intval', $siblings)), TRUE);
        $variables['reply_first'] = $position === 0;
      }
    }
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
    // Contador de reações recebidas (somente leitura), com os dados da própria barra de reação.
    $barProps = $variables['reaction_bar']['#props'] ?? [];
    if (!empty($barProps['reactions'])) {
      $variables['reactions_received'] = [
        '#type' => 'component',
        '#component' => 'aculta420_v2:discussion-reactions-received',
        '#props' => [
          'target_id' => 'discussion-reactions-comment-' . $comment->id(),
          'reactions' => $barProps['reactions'],
          'allowed_reactions' => $barProps['allowed_reactions'] ?? [],
        ],
      ];
    }
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

  /**
   * Lado do cartão de uma resposta: a raiz alterna (1.ª espelhada); uma resposta em nível é o oposto do pai.
   */
  private function discussionSide(CommentInterface $comment, int $guard = 0): string {
    if ($guard > 20) {
      return 'start';
    }
    $pid = (int) ($comment->get('pid')->target_id ?? 0);
    if ($pid === 0) {
      $roots = $this->entityTypeManager->getStorage('comment')->getQuery()
        ->accessCheck(FALSE)
        ->condition('pid', NULL, 'IS NULL')
        ->condition('entity_id', $comment->getCommentedEntityId())
        ->condition('field_name', 'field_aculta_discussion')
        ->condition('status', 1)
        ->sort('thread', 'ASC')
        ->execute();
      $position = array_search((int) $comment->id(), array_values(array_map('intval', $roots)), TRUE);
      return ($position !== FALSE && $position % 2 === 0) ? 'end' : 'start';
    }
    $parent = $this->entityTypeManager->getStorage('comment')->load($pid);
    if (!$parent instanceof CommentInterface) {
      return 'start';
    }
    return $this->discussionSide($parent, $guard + 1) === 'end' ? 'start' : 'end';
  }

}
