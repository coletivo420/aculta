<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Adds the discussion moderation trigger to consumer hosts (not to Forum topics, which have their own popup).
 */
final class DiscussionHostAdminHooks {

  public function __construct(private readonly AccountProxyInterface $currentUser) {}

  /**
   * Adds the trigger and the popup container when the viewer can moderate comments.
   *
   * @param array<string, mixed> $build
   *   Node render array.
   * @param \Drupal\node\NodeInterface $entity
   *   The host node.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $entity): void {
    if ($entity->bundle() === 'aculta_forum_topic' || !$entity->hasField('field_aculta_discussion')) {
      return;
    }
    if (!$this->currentUser->hasPermission('administer comments')) {
      return;
    }
    $build['discussion_admin_popup'] = [
      '#theme' => 'portal_component',
      '#name' => 'popup',
      '#props' => [
        'id' => 'aculta-admin-dialog',
        'title' => (string) t('Controles da discussão'),
        'close_label' => (string) t('Fechar'),
      ],
      '#slots' => [
        'content' => ['#markup' => '<div id="aculta-admin-dialog-body" class="aculta-admin-dialog__body"></div>'],
      ],
      '#weight' => 99,
    ];
    $build['discussion_admin_trigger'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:comment-admin-trigger',
      '#props' => [
        'kind' => 'topico',
        'author_name' => (string) $entity->label(),
        'title' => (string) t('Controles da discussão'),
        'url' => Url::fromRoute('aculta_forum.discussion_host_admin_popup', ['node' => $entity->id()])->toString(),
      ],
      '#attached' => ['library' => ['aculta_forum/comment-admin-popup']],
      '#cache' => ['contexts' => ['user.permissions'], 'tags' => $entity->getCacheTags()],
      '#weight' => 100,
    ];
  }

}
