<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\field;

use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\aculta_forum\Service\ForumPostUnreadManager;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the unread ForumPost count and links to the first unread post.
 */
#[ViewsField('aculta_forum_unread_posts')]
final class ForumUnreadPosts extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Unread post data keyed by topic ID for the current result page.
   *
   * @var array<int, array{count: int, post_id: int, page: int}>
   */
  private array $unreadByTopic = [];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ForumPostUnreadManager $unreadManager,
    private readonly PagerManagerInterface $pagerManager,
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
      $container->get('aculta_forum.post_unread_manager'),
      $container->get('pager.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
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
    $this->unreadByTopic = $this->unreadManager->getUnreadByTopics($topicIds);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array {
    $topicId = (int) $this->getValue($values, 'nid');
    $unread = $this->unreadByTopic[$topicId] ?? NULL;
    if ($unread === NULL || $unread['count'] < 1) {
      return [];
    }

    $query = $this->pagerManager->getUpdatedParameters([], ForumPostThreadBuilder::PAGER_ID, $unread['page']);
    $url = Url::fromRoute('entity.node.canonical', ['node' => $topicId], [
      'query' => $query,
      'fragment' => 'forum-post-' . $unread['post_id'],
    ]);
    $label = $this->formatPlural($unread['count'], '@count nova publicação', '@count novas publicações');
    return Link::fromTextAndUrl($label, $url)->toRenderable();
  }

}
