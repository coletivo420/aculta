<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters topics with no published Comment or ForumPost replies.
 */
#[ViewsFilter('aculta_forum_no_replies')]
final class PublicForumNoReplies extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $statisticsAlias = $this->ensureMyTable();
    $nodeAlias = $this->query->ensureTable('node_field_data', $this->relationship);
    if (!is_string($statisticsAlias) || !preg_match('/^[A-Za-z0-9_]+$/', $statisticsAlias)
      || !is_string($nodeAlias) || !preg_match('/^[A-Za-z0-9_]+$/', $nodeAlias)) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    $expression = $statisticsAlias . '.comment_count = 0'
      . ' AND NOT EXISTS (SELECT 1 FROM {aculta_forum_post_field_data} forum_no_reply'
      . ' WHERE forum_no_reply.topic_target_id = ' . $nodeAlias . '.nid'
      . ' AND forum_no_reply.status = 1'
      . ' AND forum_no_reply.default_langcode = 1)';
    $this->query->addWhereExpression($this->options['group'], $expression);
  }

}
