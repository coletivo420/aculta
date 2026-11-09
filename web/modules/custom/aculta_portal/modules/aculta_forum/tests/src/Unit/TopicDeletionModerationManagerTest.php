<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\TopicDeletionModerationManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests TopicDeletionModerationManager permissions and policies.
 *
 * @coversDefaultClass \Drupal\aculta_forum\Service\TopicDeletionModerationManager
 * @group aculta_forum
 */
class TopicDeletionModerationManagerTest extends UnitTestCase {

  protected function createManager(string $policy, ?AccountInterface $currentUser = NULL): TopicDeletionModerationManager {
    $database = $this->createMock(Connection::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $logger = $this->createMock(LoggerInterface::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('topic_author_delete_policy')
      ->willReturn($policy);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('aculta_forum.settings')
      ->willReturn($config);

    $user = $currentUser ?? $this->createMock(AccountInterface::class);

    return new TopicDeletionModerationManager(
      $database,
      $entityTypeManager,
      $configFactory,
      $user,
      $logger
    );
  }

  public function testModeratorCanDeleteDirectly(): void {
    $moderator = $this->createMock(AccountInterface::class);
    $moderator->method('hasPermission')->willReturnCallback(function ($perm) {
      return $perm === 'administer nodes';
    });

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwnerId')->willReturn(99);

    $manager = $this->createManager('moderation_queue', $moderator);

    $this->assertTrue($manager->canDeleteOrRequest($topic, $moderator));
    $this->assertTrue($manager->isDirectDelete($topic, $moderator));
  }

  public function testAuthorWithModerationQueuePolicy(): void {
    $author = $this->createMock(AccountInterface::class);
    $author->method('id')->willReturn(42);
    $author->method('isAuthenticated')->willReturn(TRUE);
    $author->method('hasPermission')->willReturn(FALSE);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwnerId')->willReturn(42);

    $manager = $this->createManager('moderation_queue', $author);

    $this->assertTrue($manager->canDeleteOrRequest($topic, $author));
    $this->assertFalse($manager->isDirectDelete($topic, $author));
  }

  public function testAuthorWithDirectDeletePolicy(): void {
    $author = $this->createMock(AccountInterface::class);
    $author->method('id')->willReturn(42);
    $author->method('isAuthenticated')->willReturn(TRUE);
    $author->method('hasPermission')->willReturn(FALSE);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwnerId')->willReturn(42);

    $manager = $this->createManager('direct_delete', $author);

    $this->assertTrue($manager->canDeleteOrRequest($topic, $author));
    $this->assertTrue($manager->isDirectDelete($topic, $author));
  }

  public function testAuthorWithDisabledPolicy(): void {
    $author = $this->createMock(AccountInterface::class);
    $author->method('id')->willReturn(42);
    $author->method('isAuthenticated')->willReturn(TRUE);
    $author->method('hasPermission')->willReturn(FALSE);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwnerId')->willReturn(42);

    $manager = $this->createManager('disabled', $author);

    $this->assertFalse($manager->canDeleteOrRequest($topic, $author));
    $this->assertFalse($manager->isDirectDelete($topic, $author));
  }

}
