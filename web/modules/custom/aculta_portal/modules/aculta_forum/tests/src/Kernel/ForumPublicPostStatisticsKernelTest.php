<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Plugin\views\field\PublicForumReplyCount;
use Drupal\aculta_forum\Service\ForumPublicPostStatistics;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\views\ResultRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies published ForumPost aggregates used by the public topic list.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPublicPostStatisticsKernelTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'comment',
    'aculta_forum_post_test',
  ];

  /**
   * Service under test.
   */
  private ForumPublicPostStatistics $statistics;

  /**
   * Author of the fixture posts.
   */
  private User $author;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'comment']);

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    $this->addDefaultCommentField('node', 'aculta_forum_topic', 'field_aculta_discussion', CommentingStatus::Open, 'aculta_discussion_post');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->author = User::create(['name' => 'autora', 'status' => 1]);
    $this->author->save();
    $this->statistics = new ForumPublicPostStatistics($this->container->get('database'));
  }

  /**
   * Counts published posts and reports the newest activity per topic.
   */
  public function testAggregatesOnlyPublishedPosts(): void {
    $first = $this->topic('Primeiro');
    $second = $this->topic('Segundo');
    $this->post($first, 1700000000);
    $this->post($first, 1700000300);
    $this->post($first, 1700000600, published: FALSE);
    $this->post($second, 1700000900);

    $result = $this->statistics->getByTopics([(int) $first->id(), (int) $second->id()]);

    self::assertCount(2, $result);
    self::assertSame(2, $result[(int) $first->id()]['count']);
    self::assertSame(1700000300, $result[(int) $first->id()]['last_activity']);
    self::assertSame(1, $result[(int) $second->id()]['count']);
    self::assertSame(1700000900, $result[(int) $second->id()]['last_activity']);
  }

  /**
   * The newest post ID resolves deterministically when timestamps tie.
   */
  public function testLastPostIdWinsTheTimestampTie(): void {
    $topic = $this->topic('Empate');
    $this->post($topic, 1700000000);
    $newest = $this->post($topic, 1700000300);
    $this->post($topic, 1700000300);

    $result = $this->statistics->getByTopics([(int) $topic->id()]);
    self::assertSame(3, $result[(int) $topic->id()]['count']);
    self::assertSame(1700000300, $result[(int) $topic->id()]['last_activity']);
    self::assertGreaterThan((int) $newest->id(), $result[(int) $topic->id()]['last_post_id']);
  }

  /**
   * Empty requested topics report zero and unrequested topics are absent.
   */
  public function testEmptyAndFilteredTopics(): void {
    $empty = $this->topic('Vazio');
    $filled = $this->topic('Com post');
    $this->post($filled, 1700000000);

    $result = $this->statistics->getByTopics([(int) $empty->id(), (int) $filled->id()]);
    self::assertSame(0, $result[(int) $empty->id()]['count']);
    self::assertSame(0, $result[(int) $empty->id()]['last_activity']);
    self::assertArrayNotHasKey(0, $result);
  }

  /**
   * The reply count field adds published posts to the Comment count.
   */
  public function testReplyCountFieldCombinesCommentsAndPosts(): void {
    $topic = $this->topic('Contagem');
    $this->comment($topic, 'Comentário A');
    $this->comment($topic, 'Comentário B');
    $this->post($topic, 1700000000);
    $this->post($topic, 1700000300);
    $this->post($topic, 1700000600, published: FALSE);

    $plugin = new PublicForumReplyCount(
      [],
      'aculta_forum_reply_count',
      [],
      $this->statistics,
    );
    $plugin->aliases['nid'] = 'nid';
    $plugin->aliases['comment_count'] = 'comment_count';
    $plugin->field_alias = 'reply_total';
    $values = [new ResultRow(['nid' => (int) $topic->id(), 'comment_count' => 2])];
    $plugin->preRender($values);

    self::assertSame(4, $values[0]->reply_total);
  }

  /**
   * Creates a Forum topic.
   */
  private function topic(string $title): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => 1,
      'uid' => $this->author->id(),
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Creates a published Comment in the topic.
   */
  private function comment(Node $topic, string $subject): CommentInterface {
    $comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $this->author->id(),
      'subject' => $subject,
      'comment_body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment->save();
    return $comment;
  }

  /**
   * Creates a ForumPost with an explicit changed timestamp.
   */
  private function post(Node $topic, int $changed, bool $published = TRUE): ForumPost {
    $post = ForumPost::create([
      'uid' => $this->author->id(),
      'topic' => $topic->id(),
      'body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => $published ? 1 : 0,
      'created' => $changed,
      'changed' => $changed,
    ]);
    $post->save();
    return $post;
  }

}
