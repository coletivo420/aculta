<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\sort;

use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sorts topics by published Comments and ForumPosts together.
 */
#[ViewsSort('aculta_forum_reply_count')]
final class PublicForumReplyCount extends SortPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $nodeAlias = $this->query->ensureTable('node_field_data', $this->relationship);
    if (!is_string($nodeAlias) || !preg_match('/^[A-Za-z0-9_]+$/', $nodeAlias)) {
      return;
    }

    $commentCount = $this->tableAlias . '.comment_count';
    $postCount = '(SELECT COUNT(*) FROM {aculta_forum_post_field_data} forum_post_count '
      . 'WHERE forum_post_count.topic = ' . $nodeAlias . '.nid '
      . 'AND forum_post_count.status = 1 '
      . 'AND forum_post_count.default_langcode = 1)';
    $this->query->addOrderBy(
      NULL,
      '(' . $commentCount . ' + ' . $postCount . ')',
      $this->options['order'],
      $this->tableAlias . '_aculta_reply_count',
    );
  }

}
