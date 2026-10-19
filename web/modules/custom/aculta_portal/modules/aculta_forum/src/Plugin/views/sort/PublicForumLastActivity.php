<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\sort;

use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\sort\Date;

/**
 * Sorts public forum topics by the most recent node or comment activity.
 *
 * The Core comment_ces_last_updated sort assigns the return value of
 * Sql::addOrderBy() to a typed property, although that method returns void.
 * This small adapter keeps the Core formula while avoiding that incompatibility
 * in the installed Drupal 11.4.10 runtime.
 *
 * @ingroup views_sort_handlers
 */
#[ViewsSort('aculta_forum_last_activity')]
final class PublicForumLastActivity extends Date {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $node_table = $this->query->ensureTable('node_field_data', $this->relationship);
    $formula = 'GREATEST(' . $node_table . '.changed, ' . $this->tableAlias . '.last_comment_timestamp)';
    $this->query->addOrderBy(
      NULL,
      $formula,
      $this->options['order'],
      $this->tableAlias . '_aculta_last_activity',
    );
  }

}
