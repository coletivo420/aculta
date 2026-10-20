<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies Core Views ordering by published Forum Comment statistics.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class PublicTopicCommentCountViewKernelTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'comment',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'filter', 'node', 'comment', 'views']);
    $this->container->get('state')->set('comment.maintain_entity_statistics', TRUE);
    $this->installTestView();

    NodeType::create(['type' => 'forum_topic_test', 'name' => 'Forum topic test'])->save();
    $this->addDefaultCommentField('node', 'forum_topic_test', 'field_topic_replies', CommentingStatus::Open, 'comment');
  }

  /**
   * The popular topic list ranks published replies and ignores pending ones.
   */
  public function testMostCommentedOrderUsesPublishedCommentCount(): void {
    $topics = [
      'one' => $this->createTopic('One published reply'),
      'three' => $this->createTopic('Three published replies'),
      'zero' => $this->createTopic('No published replies'),
    ];
    $this->createComment($topics['one'], 'Published one', TRUE);
    $this->createComment($topics['three'], 'Published three A', TRUE);
    $this->createComment($topics['three'], 'Published three B', TRUE);
    $this->createComment($topics['three'], 'Published three C', TRUE);
    $this->createComment($topics['zero'], 'Pending only', FALSE);

    $view = Views::getView('aculta_forum_comment_count_test');
    self::assertNotNull($view);
    $view->setDisplay('default');
    $view->preExecute();
    $view->execute();

    self::assertSame([
      (int) $topics['three']->id(),
      (int) $topics['one']->id(),
      (int) $topics['zero']->id(),
    ], array_map(static fn (object $row): int => (int) $row->nid, $view->result));
  }

  /**
   * Creates the minimal View that uses the same Core aggregate as production.
   */
  private function installTestView(): void {
    $view = View::create([
      'id' => 'aculta_forum_comment_count_test',
      'label' => 'ACULTA Forum comment count test',
      'module' => 'views',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
    ]);
    $view->getExecutable()->newDisplay('default', 'Default', 'default');
    $display = $view->getExecutable()->displayHandlers->get('default');
    $display->setOption('fields', [
      'nid' => [
        'id' => 'nid',
        'table' => 'node_field_data',
        'field' => 'nid',
        'plugin_id' => 'numeric',
      ],
    ]);
    $display->setOption('filters', [
      'status' => [
        'id' => 'status',
        'table' => 'node_field_data',
        'field' => 'status',
        'plugin_id' => 'boolean',
        'value' => 1,
      ],
    ]);
    $display->setOption('sorts', [
      'comment_count' => [
        'id' => 'comment_count',
        'table' => 'comment_entity_statistics',
        'field' => 'comment_count',
        'plugin_id' => 'standard',
        'order' => 'DESC',
      ],
    ]);
    $view->save();
  }

  /**
   * Creates a published topic for the test listing.
   */
  private function createTopic(string $title): Node {
    $topic = Node::create([
      'type' => 'forum_topic_test',
      'title' => $title,
      'status' => 1,
      'field_topic_replies' => ['status' => CommentingStatus::Open->value],
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Creates a published or pending reply.
   */
  private function createComment(Node $topic, string $subject, bool $published): void {
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_topic_replies',
      'comment_type' => 'comment',
      'subject' => $subject,
      'comment_body' => ['value' => $subject, 'format' => 'plain_text'],
      'status' => $published ? 1 : 0,
    ]);
    $comment->save();
  }

}
