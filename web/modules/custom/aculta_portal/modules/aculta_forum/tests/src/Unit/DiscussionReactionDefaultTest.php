<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Analytics\ForumAnalyticsScopeResolver;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Protects broccoli as immutable quick-like and filters reserved actions.
 */
#[Group('aculta_forum')]
final class DiscussionReactionDefaultTest extends UnitTestCase {

  public function testBroccoliSurvivesInvalidConfigAndUndoIsNotAnEmoji(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn (string $key): mixed => match ($key) {
      'default_reaction' => 'heart',
      'allowed_reactions' => [
        ['id' => 'heart', 'icon' => 'heart', 'label' => 'Coração', 'enabled' => TRUE],
        ['id' => 'broccoli', 'icon' => 'heart', 'label' => 'Mudado', 'enabled' => FALSE],
        ['id' => 'undo', 'icon' => 'heart', 'label' => 'Desfazer', 'enabled' => TRUE],
        ['id' => 'dislike', 'icon' => 'thumbs-down-medium', 'label' => 'Descurtir', 'enabled' => TRUE],
        ['id' => 'other', 'icon' => 'thumbs-down-medium', 'label' => 'Descurtir', 'enabled' => TRUE],
      ],
      default => NULL,
    });
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_forum.settings')->willReturn($config);

    $manager = new DiscussionReactionManager(
      $this->createMock(Connection::class),
      $factory,
      $this->createMock(ForumAnalyticsScopeResolver::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DiscussionOriginResolverInterface::class),
    );

    $allowed = $manager->getAllowedReactions();
    self::assertSame('broccoli', $manager->getDefaultReaction());
    self::assertSame([
      'id' => 'broccoli',
      'icon' => 'broccoli',
      'label' => 'Curtir',
      'enabled' => TRUE,
    ], $allowed['broccoli']);
    self::assertArrayHasKey('heart', $allowed);
    self::assertArrayNotHasKey('dislike', $allowed);
    self::assertArrayNotHasKey('undo', $allowed);
    self::assertArrayNotHasKey('other', $allowed);
    self::assertFalse($manager->isAllowedReaction('undo'));
    self::assertTrue($manager->isAllowedReaction('dislike'));
  }

}
