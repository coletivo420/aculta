<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Plugin\views\field\ForumUnreadPosts;
use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\aculta_forum\Service\ForumPostUnreadManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\views\ResultRow;
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
    'domain',
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
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'domain']);

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
   * The unread field links to the first unread post on its page.
   */
  public function testUnreadFieldLinksToFirstUnreadPost(): void {
    $now = \Drupal::time()->getRequestTime();
    $this->post(10, $now - 7200);
    $this->container->get('database')->insert('history')->fields([
      'uid' => $this->member->id(),
      'nid' => $this->topic->id(),
      'timestamp' => $now - 3600,
    ])->execute();
    $first = $this->post(20, $now - 1800);
    $this->post(30, $now - 1700);

    $plugin = $this->unreadField();
    $values = [new ResultRow(['nid' => (int) $this->topic->id()])];
    $plugin->preRender($values);
    $build = $plugin->render($values[0]);

    self::assertSame('2 novas publicações', (string) $build['#title']);
    self::assertSame('entity.node.canonical', $build['#url']->getRouteName());
    self::assertSame('forum-post-' . $first->id(), $build['#url']->getOption('fragment'));
    self::assertArrayHasKey('page', $build['#url']->getOption('query'));
  }

  /**
   * Topics without unread posts render no link.
   */
  public function testUnreadFieldIsEmptyWithoutUnreadPosts(): void {
    $this->post(10, \Drupal::time()->getRequestTime() - 40 * 86400);
    $plugin = $this->unreadField();
    $values = [new ResultRow(['nid' => (int) $this->topic->id()])];
    $plugin->preRender($values);
    self::assertSame([], $plugin->render($values[0]));
  }

  /**
   * Builds the unread field over the real services.
   */
  private function unreadField(): ForumUnreadPosts {
    $manager = new ForumPostUnreadManager($this->container->get('database'), $this->member);
    $builder = new ForumPostThreadBuilder(
      $this->container->get('entity_type.manager'),
      $this->member,
      new DomainPurposeManager(
        $this->container->get('entity_type.manager'),
        $this->container->get('domain.negotiator'),
        $this->container->get('config.factory'),
        new RequestStack(),
      ),
      new DiscussionContextResolver([]),
      $this->container->get('pager.manager'),
    );
    $plugin = new ForumUnreadPosts([], 'aculta_forum_unread_posts', [], $manager, $builder);
    $plugin->aliases['nid'] = 'nid';
    return $plugin;
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
