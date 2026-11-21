<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Fragment for the discussion moderation popup on consumer hosts (Wiki, Revista, LMS, comments).
 */
final class DiscussionHostAdminPopupController extends ControllerBase {

  /**
   * Returns the discussion actions for a host node.
   *
   * @return array<string, mixed>
   *   Render array with the allowed actions.
   */
  public function view(NodeInterface $node): array {
    $items = [
      [
        '#type' => 'link',
        '#title' => $this->t('Abrir ou fechar discussão'),
        '#url' => Url::fromRoute('aculta_forum.discussion_host_status', ['node' => $node->id()]),
      ],
    ];
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:discussion-admin-controls',
      '#props' => ['state_label' => (string) $this->t('Aberta')],
      '#slots' => ['actions' => $items],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => $node->getCacheTags(),
      ],
    ];
  }

}
