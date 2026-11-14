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
use Drupal\Core\Render\Element;
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
    #[Autowire(service: 'keyvalue')]
    private readonly \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory,
    #[Autowire(service: 'csrf_token')]
    private readonly \Drupal\Core\Access\CsrfTokenGenerator $csrfGenerator,
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
      // Comentários de publicação da Revista: sem tópico; a publicação é o próprio tópico.
      $host = $threadComment->getCommentedEntity();
      $variables['publication_comment'] = $host instanceof NodeInterface && $host->bundle() === 'revista';
      // Controles administrativos valem para todo comentário da discussão (fórum, LMS, Revista e Wiki).
      $variables['admin_url'] = $this->currentUser->hasPermission('administer comments')
        ? \Drupal\Core\Url::fromRoute('aculta_forum.comment_moderation', ['comment' => (int) $threadComment->id()])->toString()
        : NULL;
      // Estado de exclusão vale para todo comentário da discussão, não só para a Revista.
      $cid = (int) $threadComment->id();
      $isOwner = (int) $threadComment->getOwnerId() === (int) $this->currentUser->id();
      $isAdmin = $this->currentUser->hasPermission('administer comments');
      // Estado: resposta excluída pelo autor fica como "Comentário excluído", sem edição, resposta nem citação.
      // Valor do tombstone: TRUE = excluído pelo autor; 'moderacao' = removido pela moderação.
      $tombstone = $this->keyValueFactory->get('aculta_forum_comment_tombstones')->get((string) $cid);
      $deleted = (bool) $tombstone;
      $variables['comment_deleted'] = $deleted;
      $variables['delete_allowed'] = $isAdmin || ($isOwner && !$deleted);
      $variables['comment_removed_by_moderation'] = $tombstone === 'moderacao';
      // Controles administrativos: só para quem pode moderar (ver DiscussionCommentModerationForm).
      $variables['delete_token'] = $this->csrfGenerator->get('aculta_forum.comment.delete.' . $cid);
      $variables['delete_url'] = '/discussoes/' . $cid . '/excluir';
      $variables['delete_message'] = $isAdmin
        ? (string) new \Drupal\Core\StringTranslation\TranslatableMarkup('O conteúdo será substituído por "Conteúdo removido pela moderação". Para excluir o bloco de vez, use os controles do comentário.')
        : (string) new \Drupal\Core\StringTranslation\TranslatableMarkup('A resposta ficará como "Comentário excluído" e não poderá mais ser editada.');
      if ($variables['publication_comment']) {
        $reactions = $this->reactionManager->loadReactions('comment', (int) $threadComment->id(), NULL);
        $total = 0;
        foreach (($reactions['reactions'] ?? []) as $reaction) {
          $total += (int) ($reaction['count'] ?? 0);
        }
        $variables['sort_total'] = $total;
        $variables['sort_created'] = (int) $threadComment->getCreatedTime();
        $cid = (int) $threadComment->id();
        // Ações da resposta: responder, citar e editar abrem o Compositor de Resposta da Discussão;
        // a rota valida o acesso. Excluir só aparece quando a conta pode.
        $cid = (int) $threadComment->id();
        $composer = '/discussoes/compositor/node/' . (int) $threadComment->getCommentedEntityId();
        $actions = ['permalink_url' => '/comment/' . $cid];
        if (!$deleted) {
          $actions['reply_url'] = $composer . '?resposta=' . $cid;
          $actions['quote_url'] = $composer . '?citar=' . $cid;
          if ($threadComment->access('update', $this->currentUser)) {
            $actions['edit_url'] = $composer . '?editar=' . $cid;
          }
        }
        $variables['revista_actions'] = $actions;
      }
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

  /**
   * Revista: comentários por total de reações (desc) e depois data (desc); respostas seguem o pai.
   *
   * A seção de comentários da publicação é renderizada pelo bloco do campo, e não pelo build da entidade.
   */
  #[Hook('block_view_alter')]
  public function revistaCommentBlockOrder(array &$build, $block): void {
    if (!is_object($block) || !method_exists($block, 'getPluginId')
      || !str_starts_with((string) $block->getPluginId(), 'field_block:node:revista:field_aculta_discussion')) {
      return;
    }
    $this->orderCommentsIn($build);
  }

  /**
   * Revista (entidade): mesma ordenação quando a seção vem do build da entidade.
   */
  #[Hook('entity_view_alter')]
  public function revistaCommentOrder(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'revista' || ($build['#view_mode'] ?? NULL) !== 'full') {
      return;
    }
    // O campo fica dentro das seções do Layout Builder: procura a lista em todo o build.
    $this->orderCommentsIn($build);
  }

  /**
   * Revista: a seção de comentários da publicação é ordenada no preprocess do nó.
   */
  #[Hook('preprocess_node')]
  public function revistaCommentPreprocessOrder(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'revista') {
      return;
    }
    if (isset($variables['content']['field_aculta_discussion']) && is_array($variables['content']['field_aculta_discussion'])) {
      $this->orderCommentsIn($variables['content']['field_aculta_discussion']);
    }
  }

  /**
   * Encontra listas de comentários (chave comments com elementos #comment) e reordena cada uma.
   */
  private function orderCommentsIn(array &$element): void {
    if (isset($element['comments']) && is_array($element['comments'])) {
      $this->reorderCommentItems($element['comments']);
    }
    foreach (Element::children($element) as $key) {
      if (is_array($element[$key])) {
        $this->orderCommentsIn($element[$key]);
      }
    }
  }

  /**
   * Reordena uma lista de comentários: raízes por total de reações e data; respostas logo após o pai.
   */
  private function reorderCommentItems(array &$items): void {
    $comments = [];
    foreach (Element::children($items) as $key) {
      $comment = $items[$key]['#comment'] ?? NULL;
      if ($comment instanceof CommentInterface) {
        $comments[(int) $comment->id()] = $comment;
      }
    }
    if (count($comments) < 2) {
      return;
    }
    $totals = [];
    foreach ($comments as $cid => $comment) {
      $reactions = $this->reactionManager->loadReactions('comment', $cid, NULL);
      $sum = 0;
      foreach (($reactions['reactions'] ?? []) as $reaction) {
        $sum += (int) ($reaction['count'] ?? 0);
      }
      $totals[$cid] = $sum;
    }
    $children = [];
    $roots = [];
    foreach ($comments as $cid => $comment) {
      $pid = (int) ($comment->get('pid')->target_id ?? 0);
      if ($pid > 0 && isset($comments[$pid])) {
        $children[$pid][] = $cid;
      }
      else {
        $roots[] = $cid;
      }
    }
    usort($roots, static function (int $a, int $b) use ($totals, $comments): int {
      return [$totals[$b], $comments[$b]->getCreatedTime(), $b] <=> [$totals[$a], $comments[$a]->getCreatedTime(), $a];
    });
    $order = [];
    $walk = function (int $cid) use (&$walk, &$order, $children): void {
      $order[] = $cid;
      foreach ($children[$cid] ?? [] as $child) {
        $walk($child);
      }
    };
    foreach ($roots as $root) {
      $walk($root);
    }
    foreach ($order as $index => $cid) {
      $items[$cid]['#weight'] = $index;
    }
  }

}
