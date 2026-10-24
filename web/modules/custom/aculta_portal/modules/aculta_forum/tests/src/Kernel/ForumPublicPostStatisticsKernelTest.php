<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Service\ForumPublicPostStatistics;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies published ForumPost aggregates used by the public topic list.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPublicPostStatisticsKernelTest extends KernelTestBase {

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
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node']);

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
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
