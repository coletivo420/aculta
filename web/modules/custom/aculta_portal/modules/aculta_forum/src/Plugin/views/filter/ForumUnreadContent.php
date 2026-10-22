<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters Forum topics with unread node, Comment, or ForumPost activity.
 */
#[ViewsFilter('aculta_forum_unread_content')]
final class ForumUnreadContent extends FilterPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    if ($this->currentUser->isAnonymous()) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    $historyAlias = $this->ensureMyTable();
    $nodeAlias = $this->query->ensureTable('node_field_data', $this->relationship);
    $statisticsAlias = $this->query->ensureTable('comment_entity_statistics', $this->relationship);
    if (!is_string($nodeAlias) || !preg_match('/^[A-Za-z0-9_]+$/', $nodeAlias)
      || !is_string($statisticsAlias) || !preg_match('/^[A-Za-z0-9_]+$/', $statisticsAlias)
      || !is_string($historyAlias) || !preg_match('/^[A-Za-z0-9_]+$/', $historyAlias)) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    $expression = "(({$historyAlias}.timestamp IS NULL AND ({$nodeAlias}.changed > :forum_history_limit_missing OR COALESCE({$statisticsAlias}.last_comment_timestamp, 0) > :forum_history_limit_comments))"
      . " OR {$historyAlias}.timestamp < {$nodeAlias}.changed"
      . " OR {$historyAlias}.timestamp < {$statisticsAlias}.last_comment_timestamp"
      . " OR EXISTS (SELECT 1 FROM {aculta_forum_post_field_data} forum_unread_post"
      . " WHERE forum_unread_post.topic_target_id = {$nodeAlias}.nid"
      . " AND forum_unread_post.status = 1"
      . " AND forum_unread_post.default_langcode = 1"
      . " AND forum_unread_post.first_published > GREATEST(COALESCE({$historyAlias}.timestamp, :forum_history_limit_post), :forum_history_limit_old)))";
    $this->query->addWhereExpression($this->options['group'], $expression, [
      ':forum_history_limit_missing' => HISTORY_READ_LIMIT,
      ':forum_history_limit_comments' => HISTORY_READ_LIMIT,
      ':forum_history_limit_post' => HISTORY_READ_LIMIT,
      ':forum_history_limit_old' => HISTORY_READ_LIMIT,
    ]);
  }

}
