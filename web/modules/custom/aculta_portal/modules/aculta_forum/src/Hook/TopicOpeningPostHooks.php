<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\TopicDeletionModerationManager;
use Drupal\node\NodeInterface;

/**
 * Dados autorizados da postagem de abertura do tópico.
 */
final class TopicOpeningPostHooks {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly TopicDeletionModerationManager $moderationManager,
    private readonly DiscussionReplyAccess $replyAccess,
  ) {}

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'aculta_forum_topic' || ($variables['view_mode'] ?? '') !== 'full') {
      return;
    }

    $variables['opening_delete_url'] = NULL;
    $variables['opening_edit_url'] = NULL;
    $variables['opening_quote_url'] = NULL;
    $variables['opening_reply_url'] = NULL;

    if ($this->moderationManager->canDeleteOrRequest($node, $this->currentUser)) {
      $variables['opening_delete_url'] = Url::fromRoute('aculta_forum.discussion_composer', ['entity_type' => 'node', 'entity_id' => $node->id()], ['query' => ['excluir_topico' => 1]])->toString();
    }

    if ($node->access('update', $this->currentUser)) {
      $variables['opening_edit_url'] = Url::fromRoute('aculta_forum.discussion_composer', ['entity_type' => 'node', 'entity_id' => $node->id()], ['query' => ['editar_topico' => 1]])->toString();
    }

    if ($this->replyAccess->checkAccess($node, $this->currentUser)->isAllowed()) {
      $variables['opening_quote_url'] = Url::fromRoute('aculta_forum.discussion_composer', ['entity_type' => 'node', 'entity_id' => $node->id()], ['query' => ['citar_topico' => 1, 'modo' => 'citar']])->toString();
      $variables['opening_reply_url'] = Url::fromRoute('aculta_forum.discussion_composer', ['entity_type' => 'node', 'entity_id' => $node->id()], ['query' => ['modo' => 'responder']])->toString();
    }

    // Ensure discussion reply popup library is attached even if there are 0 comments yet.
    $variables['#attached']['library'][] = 'aculta_forum/discussion-reply-popup';
  }

}
