<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/** Maintains Magazine publication metadata owned by the Revista bundle. */
final class RevistaContentHooks {

  /** Records the first publication time when a Revista Node is published. */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->bundle() === 'revista'
      && $node->isPublished()
      && $node->hasField('field_published_at')
      && $node->get('field_published_at')->isEmpty()) {
      $node->set('field_published_at', gmdate('Y-m-d\\TH:i:s'));
    }
  }

  /** Removes an empty optional image from the Revista JSON-LD metadata. */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$tags, array &$context): void {
    $entity = $context['entity'] ?? NULL;
    if (!$entity instanceof NodeInterface
      || $entity->bundle() !== 'revista'
      || !$entity->hasField('field_media_image')
      || !$entity->get('field_media_image')->isEmpty()) {
      return;
    }

    if (str_contains((string) ($tags['schema_article_image'] ?? ''), '[node:image]')) {
      unset($tags['schema_article_image']);
    }
  }

}
