<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Fragment for the topic administration popup (opened by comment-admin-trigger kind=topico).
 *
 * Route access is checked by permission; the fragment only links to protected routes.
 */
final class TopicAdminPopupController extends ControllerBase {

  /**
   * Returns the topic actions the current user is allowed to run.
   *
   * @return array<string, mixed>
   *   Render array with the allowed actions.
   */
  public function view(NodeInterface $node): array {
    $items = [];
    if ($this->currentUser()->hasPermission('move aculta forum topics')) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('Mover tópico de categoria'),
        '#url' => Url::fromRoute('aculta_forum.topic_move', ['node' => $node->id()]),
        '#attributes' => ['class' => ['forum-topic-admin__link']],
      ];
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#empty' => $this->t('Nenhuma ação disponível para este tópico.'),
      '#attributes' => ['class' => ['forum-topic-admin']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => $node->getCacheTags(),
      ],
    ];
  }

}
