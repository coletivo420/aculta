<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\Date;

/**
 * Displays the latest activity among a topic, Comments, and ForumPosts.
 */
#[ViewsField('aculta_forum_last_activity')]
final class PublicForumLastActivity extends Date {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $nodeTable = $this->query->ensureTable('node_field_data', $this->relationship);
    $formula = 'GREATEST(' . $nodeTable . '.changed, COALESCE(' . $this->tableAlias . '.last_comment_timestamp, 0), '
      . 'COALESCE((SELECT MAX(forum_post_activity.changed) FROM {aculta_forum_post_field_data} forum_post_activity '
      . 'WHERE forum_post_activity.topic_target_id = ' . $nodeTable . '.nid '
      . 'AND forum_post_activity.status = 1 '
      . 'AND forum_post_activity.default_langcode = 1), 0))';
    $this->field_alias = $this->query->addField(NULL, $formula, $this->tableAlias . '_' . $this->field);
  }

}
