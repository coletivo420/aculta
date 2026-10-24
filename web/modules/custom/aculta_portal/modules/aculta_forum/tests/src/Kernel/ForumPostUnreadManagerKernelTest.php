<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\aculta_forum\Service\ForumPostUnreadManager;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies unread ForumPost counts and first-unread pages per account.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostUnreadManagerKernelTest extends KernelTestBase {

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
    'history',
    'aculta_forum_post_test',
  ];

  /**
   * Account with a History receipt.
   */
  private User $member;

  /**
   * Topic that hosts the posts.
   */
  private Node $topic;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('history', ['history']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node']);

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    $this->member = User::create(['name' => 'membro', 'status' => 1]);
    $this->member->save();
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico',
      'status' => 1,
      'uid' => $this->member->id(),
    ]);
    $this->topic->save();
  }

  /**
   * Anonymous sessions never receive unread data.
   */
  public function testAnonymousHasNoUnreadData(): void {
    $this->post(10, time() - 3600);
    $manager = new ForumPostUnreadManager($this->container->get('database'), new AnonymousUserSession());
    self::assertSame([], $manager->getUnreadByTopics([(int) $this->topic->id()]));
  }

  /**
   * Posts newer than the receipt or the History limit are unread.
   */
  public function testUnreadCountAndFirstPostWithoutReceipt(): void {
    $old = \Drupal::time()->getRequestTime() - 40 * 86400;
    $recent = \Drupal::time()->getRequestTime() - 3600;
    $this->post(10, $old);
    $first_unread = $this->post(20, $recent);

    $manager = new ForumPostUnreadManager($this->container->get('database'), $this->member);
    $result = $manager->getUnreadByTopics([(int) $this->topic->id()]);

    self::assertCount(1, $result);
    self::assertSame(1, $result[(int) $this->topic->id()]['count']);
    self::assertSame((int) $first_unread->id(), $result[(int) $this->topic->id()]['post_id']);
    self::assertSame(0, $result[(int) $this->topic->id()]['page']);
  }

  /**
   * The receipt marks older posts read and the page counts preceding posts.
   */
  public function testReceiptAndFirstUnreadPage(): void {
    $now = \Drupal::time()->getRequestTime();
    $read = $this->post(10, $now - 7200);
    // The receipt sits after the first post and before the second.
    $this->container->get('database')->insert('history')->fields([
      'uid' => $this->member->id(),
      'nid' => $this->topic->id(),
      'timestamp' => $now - 3600,
    ])->execute();
    $unread = $this->post(20, $now - 1800);

    $manager = new ForumPostUnreadManager($this->container->get('database'), $this->member);
    $result = $manager->getUnreadByTopics([(int) $this->topic->id()]);
    self::assertSame(1, $result[(int) $this->topic->id()]['count']);
    self::assertSame((int) $unread->id(), $result[(int) $this->topic->id()]['post_id']);
    self::assertSame(0, $result[(int) $this->topic->id()]['page']);
    self::assertLessThan((int) $unread->get('first_published')->value, (int) $read->get('first_published')->value);
  }

  /**
   * A long read history advances the first-unread page.
   */
  public function testFirstUnreadPageUsesPrecedingPosts(): void {
    $now = \Drupal::time()->getRequestTime();
    $count = ForumPostThreadBuilder::POSTS_PER_PAGE;
    for ($index = 1; $index <= $count; $index++) {
      $this->post($index * 10, $now - 7200);
    }
    $this->container->get('database')->insert('history')->fields([
      'uid' => $this->member->id(),
      'nid' => $this->topic->id(),
      'timestamp' => $now - 3600,
    ])->execute();
    $unread = $this->post(($count + 1) * 10, $now - 1800);

    $manager = new ForumPostUnreadManager($this->container->get('database'), $this->member);
    $result = $manager->getUnreadByTopics([(int) $this->topic->id()]);
    self::assertSame(1, $result[(int) $this->topic->id()]['count']);
    self::assertSame((int) $unread->id(), $result[(int) $this->topic->id()]['post_id']);
    self::assertSame(1, $result[(int) $this->topic->id()]['page']);
  }

  /**
   * Creating a receipt after the newest post clears the unread entry.
   */
  public function testReceiptAfterNewestPostClearsUnread(): void {
    $now = \Drupal::time()->getRequestTime();
    $this->post(10, $now - 1800);
    $this->container->get('database')->insert('history')->fields([
      'uid' => $this->member->id(),
      'nid' => $this->topic->id(),
      'timestamp' => $now,
    ])->execute();

    $manager = new ForumPostUnreadManager($this->container->get('database'), $this->member);
    self::assertSame([], $manager->getUnreadByTopics([(int) $this->topic->id()]));
  }

  /**
   * Creates a published post with thread position and first publication.
   */
  private function post(int $thread_position, int $first_published): ForumPost {
    $post = ForumPost::create([
      'uid' => $this->member->id(),
      'topic' => $this->topic->id(),
      'body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => 1,
      'thread_position' => $thread_position,
      'created' => $first_published,
      'first_published' => $first_published,
    ]);
    $post->save();
    return $post;
  }

}
