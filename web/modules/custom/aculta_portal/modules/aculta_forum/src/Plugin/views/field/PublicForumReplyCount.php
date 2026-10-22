<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\field;

use Drupal\aculta_forum\Service\ForumPublicPostStatistics;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\NumericField;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Shows Comment Core replies plus published revisionable ForumPosts.
 */
#[ViewsField('aculta_forum_reply_count')]
final class PublicForumReplyCount extends NumericField implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ForumPublicPostStatistics $postStatistics,
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
      $container->get('aculta_forum.public_post_statistics'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField($this->tableAlias, 'comment_count');
    $this->aliases['comment_count'] = $this->field_alias;
    $this->additional_fields['nid'] = [
      'table' => 'node_field_data',
      'field' => 'nid',
    ];
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values): void {
    $topicIds = [];
    foreach ($values as $row) {
      $topicIds[] = (int) $this->getValue($row, 'nid');
    }
    $statistics = $this->postStatistics->getByTopics($topicIds);
    foreach ($values as $row) {
      $topicId = (int) $this->getValue($row, 'nid');
      $commentCount = (int) ($this->getValue($row, 'comment_count') ?? 0);
      $postCount = $statistics[$topicId]['count'] ?? 0;
      $row->{$this->field_alias} = $commentCount + $postCount;
    }
  }

}
