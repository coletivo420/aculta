<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Link de edição da postagem de abertura do tópico, só quando o usuário pode editar.
 */
final class TopicOpeningPostHooks {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
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
    $variables['opening_edit_url'] = NULL;
    if ($node->access('update', $this->currentUser)) {
      $variables['opening_edit_url'] = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()])->toString();
    }
  }

}
