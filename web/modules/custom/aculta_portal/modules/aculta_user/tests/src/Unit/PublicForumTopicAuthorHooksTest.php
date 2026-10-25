<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\Hook\PublicForumTopicAuthorHooks;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\aculta_user\Social\SocialNetworks;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ACULTA User author-card integration for public ACULTA Forum topics.
 */
#[Group('aculta_user')]
final class PublicForumTopicAuthorHooksTest extends UnitTestCase {

  /**
   * Tests public topics use the shared author_forum variant. */
  public function testPublicTopicUsesSharedForumAuthorCard(): void {
    $account = $this->accountWithPublicNickname();
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('hasField')->with('field_autor_legado')->willReturn(FALSE);
    $node->method('getOwner')->willReturn($account);
    $build = [];

    $this->hook()->entityView($build, $node, $this->createMock(EntityViewDisplayInterface::class), 'full');

    self::assertSame('aculta_user_author_card', $build['author_card']['#theme']);
    self::assertSame('author_forum', $build['author_card']['#variant']);
    self::assertSame('Maria', $build['author_card']['#display_name']);
    self::assertSame('2020', $build['author_card']['#member_since']);
    self::assertSame(-20, $build['author_card']['#weight']);
  }

  /**
   * Tests the hook does not add cards to other bundles or non-card view modes. */
  public function testOtherBundlesAndViewModesAreIgnored(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->expects(self::once())->method('bundle')->willReturn('forum');
    $build = [];

    $this->hook()->entityView($build, $node, $this->createMock(EntityViewDisplayInterface::class), 'full');

    self::assertArrayNotHasKey('author_card', $build);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->expects(self::never())->method('hasField');
    $build = [];
    $this->hook()->entityView($build, $topic, $this->createMock(EntityViewDisplayInterface::class), 'search_index');

    self::assertArrayNotHasKey('author_card', $build);
  }

  /**
   * Tests the hook is a Drupal 11 OOP entity view hook. */
  public function testHookUsesEntityViewAttribute(): void {
    $method = new \ReflectionMethod(PublicForumTopicAuthorHooks::class, 'entityView');
    $attributes = $method->getAttributes(Hook::class);

    self::assertCount(1, $attributes);
    self::assertSame('entity_view', $attributes[0]->newInstance()->hook);
  }

  /**
   * Builds the production AuthorCardBuilder with isolated profile services. */
  private function hook(): PublicForumTopicAuthorHooks {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $nickname = $this->createMock(FieldItemListInterface::class);
    $nickname->method('__get')->with('value')->willReturn('Maria');
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'field_nickname');
    $profile->method('get')->willReturn($nickname);
    $profile->method('getCacheTags')->willReturn(['profile:7']);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheMaxAge')->willReturn(-1);
    $profileStorage = $this->createMock(ProfileStorageInterface::class);
    $profileStorage->method('loadByUser')->willReturn($profile);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($profileStorage);

    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $entityTypeManager),
      new PublicProfilePolicy(),
      new SocialNetworks(),
      $dateFormatter = $this->createMock(DateFormatterInterface::class),
    );
    $dateFormatter->method('format')->willReturn('2020');
    return new PublicForumTopicAuthorHooks(new AuthorCardBuilder($resolver));
  }

  /**
   * Creates the user identity consumed by the public-card policy. */
  private function accountWithPublicNickname(): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);
    $account->method('getCreatedTime')->willReturn(1577836800);
    return $account;
  }

}
