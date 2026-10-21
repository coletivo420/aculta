<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_user\EditWindowExemptionInterface;
use Drupal\aculta_user\ParticipationEditWindow;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Component\Datetime\TimeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests time-limited author edits for shared discussion entities.
 */
#[Group('aculta_forum')]
final class ParticipationEditWindowTest extends TestCase {

  /**
   * The default window expires at ten minutes and never grants base access.
   */
  public function testDefaultWindowUsesFirstPublicationTimestamp(): void {
    $window = $this->window(NULL, 1000);
    $post = $this->post(401);
    $author = $this->account(17);

    $open = $window->access($post, 'update', $author);
    self::assertTrue($open->isNeutral());
    self::assertSame(1, $open->getCacheMaxAge());
    self::assertTrue($window->access($post, 'update', $this->account(18))->isNeutral());
    self::assertTrue($window->access($this->post(400), 'update', $author)->isForbidden());
  }

  /**
   * Zero disables author edits; administrators continue to the base handler.
   */
  public function testZeroBlocksAuthorAndAdminRemainsNeutral(): void {
    $window = $this->window(0, 1000);
    $post = $this->post(999);

    self::assertTrue($window->access($post, 'update', $this->account(17))->isForbidden());
    self::assertTrue($window->access($post, 'update', $this->account(17, TRUE))->isNeutral());
  }

  /**
   * Without a marker, the creation time bounds the window instead of leaving it open.
   */
  public function testMissingMarkerUsesCreationTimeAsBound(): void {
    $window = $this->window(10, 1000);
    $legacy = $this->legacyPost(400);

    self::assertTrue($window->access($legacy, 'update', $this->account(17))->isForbidden());
    self::assertTrue($this->window(10, 500)->access($legacy, 'update', $this->account(17))->isNeutral());
  }

  /**
   * An allowed consumer exemption lifts the author window; a denied one does not.
   */
  public function testAllowedExemptionLiftsWindow(): void {
    $allowed = new ParticipationEditWindowExemptionStub(AccessResult::allowed());
    $denied = new ParticipationEditWindowExemptionStub(AccessResult::neutral());
    $post = $this->post(1);

    self::assertTrue($this->window(10, 1000, [$allowed])->access($post, 'update', $this->account(17))->isNeutral());
    self::assertTrue($this->window(10, 1000, [$denied])->access($post, 'update', $this->account(17))->isForbidden());
  }

  /**
   * Builds the edit policy with a configured consumer window and clock.
   */
  private function window(?int $minutes, int $now, iterable $exemptions = []): ParticipationEditWindow {
    $config = new class($minutes) extends Config {

      public function __construct(private readonly ?int $minutes) {}

      /**
       * {@inheritdoc}
       */
      public function get($key = '') {
        return $key === 'edit_window_minutes' ? $this->minutes : NULL;
      }

      /**
       * {@inheritdoc}
       */
      public function getCacheTags() {
        return ['config:aculta_forum.settings'];
      }

      /**
       * {@inheritdoc}
       */
      public function getCacheContexts() {
        return [];
      }

      /**
       * {@inheritdoc}
       */
      public function getCacheMaxAge() {
        return -1;
      }

    };
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_forum.settings')->willReturn($config);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($now);
    return new ParticipationEditWindow($configFactory, $time, $exemptions);
  }

  /**
   * Creates a post owner entity with the supplied first-publication time.
   */
  private function post(int $firstPublished): FieldableEntityInterface&EntityOwnerInterface {
    $post = $this->createMockForIntersectionOfInterfaces([FieldableEntityInterface::class, EntityOwnerInterface::class]);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('getOwnerId')->willReturn(17);
    $post->method('hasField')->with('first_published')->willReturn(TRUE);
    $post->method('get')->with('first_published')->willReturn(new ParticipationEditWindowFieldList($firstPublished));
    $post->method('getCacheTags')->willReturn([]);
    $post->method('getCacheContexts')->willReturn([]);
    $post->method('getCacheMaxAge')->willReturn(-1);
    return $post;
  }

  /**
   * Creates a post without a first-publication marker, only a creation time.
   */
  private function legacyPost(int $created): FieldableEntityInterface&EntityOwnerInterface {
    $post = $this->createMockForIntersectionOfInterfaces([FieldableEntityInterface::class, EntityOwnerInterface::class]);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('getOwnerId')->willReturn(17);
    $post->method('hasField')->willReturnCallback(static fn(string $field): bool => in_array($field, ['first_published', 'created'], TRUE));
    $post->method('get')->willReturnCallback(static fn(string $field): ParticipationEditWindowFieldList => $field === 'created'
      ? new ParticipationEditWindowFieldList($created)
      : new ParticipationEditWindowFieldList(0, TRUE));
    $post->method('getCacheTags')->willReturn([]);
    $post->method('getCacheContexts')->willReturn([]);
    $post->method('getCacheMaxAge')->willReturn(-1);
    return $post;
  }

  /**
   * Creates an account with optional administrator permission.
   */
  private function account(int $uid, bool $admin = FALSE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasPermission')->with('administer aculta forum posts')->willReturn($admin);
    return $account;
  }

}

/**
 * Minimal field list exposing a first-publication timestamp.
 */
final class ParticipationEditWindowFieldList extends FieldItemList {

  public function __construct(private readonly int $timestamp, private readonly bool $empty = FALSE) {}

  /**
   * {@inheritdoc}
   */
  public function __get($property_name): mixed {
    return $property_name === 'value' ? $this->timestamp : parent::__get($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return $this->empty;
  }

}

/**
 * Exemption stub that returns a fixed access result.
 */
final class ParticipationEditWindowExemptionStub implements EditWindowExemptionInterface {

  public function __construct(private readonly AccessResultInterface $result) {}

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    return $this->result;
  }

}
