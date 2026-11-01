<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsCache;
use Drupal\views\Plugin\views\cache\Tag;

/**
 * Tag-based Views caching invalidated when a ForumPost changes.
 */
#[ViewsCache(
  id: 'aculta_forum_post_tag',
  title: new TranslatableMarkup('ACULTA Forum posts'),
  help: new TranslatableMarkup('Tag-based cache invalidated when a ForumPost is created, updated, or deleted.'),
)]
final class ForumPostTag extends Tag {

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['aculta_forum_post_list']);
  }

}
