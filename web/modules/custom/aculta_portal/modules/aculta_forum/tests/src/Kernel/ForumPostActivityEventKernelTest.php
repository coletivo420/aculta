<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Event\ForumActivityEvent;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Hook\ForumActivityEventHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves ForumPosts only emit public creation activity once published.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostActivityEventKernelTest extends KernelTestBase {

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
   * Hooks under test.
   */
  private ForumActivityEventHooks $hooks;

  /**
   * Emitted post activity events.
   *
   * @var \Drupal\aculta_forum\Event\ForumActivityEvent[]
   */
  private array $events = [];

  /**
   * Author of the fixture posts.
   */
  private User $author;

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
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node']);

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    $this->author = User::create(['name' => 'autora', 'status' => 1]);
    $this->author->save();
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico',
      'status' => 1,
      'uid' => $this->author->id(),
    ]);
    $this->topic->save();

    $this->container->get('event_dispatcher')->addListener(
      ForumActivityEvent::POST_CREATED,
      function (ForumActivityEvent $event): void {
        $this->events[] = $event;
      },
    );
    $this->hooks = new ForumActivityEventHooks(
      $this->container->get('event_dispatcher'),
      $this->container->get('datetime.time'),
    );
  }

  /**
   * Pending posts stay silent until their first publication.
   */
  public function testPendingPostCountsOnlyOnApproval(): void {
    $post = $this->post(published: FALSE);
    $this->hooks->entityInsert($post);
    self::assertEmpty($this->events);

    $original = clone $post;
    $post->setPublished();
    $post->setOriginal($original);
    $this->hooks->entityPublished($post);
    self::assertCount(1, $this->events);
    self::assertSame((int) $post->id(), $this->events[0]->entityId);

    $post->setOriginal(clone $post);
    $this->hooks->entityPublished($post);
    self::assertCount(1, $this->events);
  }

  /**
   * Published posts count once and synchronized inserts never count.
   */
  public function testPublishedInsertCountsOnceAndSyncIsIgnored(): void {
    $published = $this->post(published: TRUE);
    $this->hooks->entityInsert($published);
    self::assertCount(1, $this->events);

    $synced = $this->post(published: TRUE);
    $synced->setSyncing(TRUE);
    $this->hooks->entityInsert($synced);
    self::assertCount(1, $this->events);
  }

  /**
   * A non-default revision is not current activity.
   */
  public function testNonDefaultRevisionIsIgnored(): void {
    $post = $this->post(published: TRUE);
    $first_revision = $post->getRevisionId();
    $post->setNewRevision(TRUE);
    $post->set('body', ['value' => 'Revisão nova', 'format' => 'plain_text']);
    $post->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_forum_post');
    $old = $storage->loadRevision($first_revision);
    self::assertNotNull($old);
    self::assertFalse($old->isDefaultRevision());
    $this->hooks->entityInsert($old);
    self::assertEmpty($this->events);
  }

  /**
   * Creates a ForumPost in the fixture topic.
   */
  private function post(bool $published): ForumPost {
    $post = ForumPost::create([
      'uid' => $this->author->id(),
      'topic' => $this->topic->id(),
      'body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => $published ? 1 : 0,
    ]);
    $post->save();
    return $post;
  }

}
