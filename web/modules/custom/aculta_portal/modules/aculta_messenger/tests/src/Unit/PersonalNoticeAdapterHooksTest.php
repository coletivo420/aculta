<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\PersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Aviso\PersonalNoticePageState;
use Drupal\aculta_messenger\Hook\PersonalNoticeAdapterHooks;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests aggregation of optional adapters into the shared personal notice UI.
 */
#[CoversClass(PersonalNoticeAdapterHooks::class)]
#[Group('aculta_messenger')]
final class PersonalNoticeAdapterHooksTest extends UnitTestCase {

  /**
   * A filtered origin delegates its offset and page size to one adapter.
   */
  public function testSelectedOriginUsesThePaginatedAdapterContract(): void {
    $account = $this->createMock(AccountInterface::class);
    $state = new PersonalNoticePageState('wiki', 100);
    $lms = $this->createMock(PersonalNoticeAdapterInterface::class);
    $lms->expects(self::never())->method('addPersonalNotices');
    $wiki = $this->createMock(PaginatedPersonalNoticeAdapterInterface::class);
    $wiki->expects(self::once())
      ->method('addPersonalNoticesPage')
      ->with(
        self::anything(),
        $account,
        self::callback(static fn(array $filters): bool => ($filters['page_state'] ?? NULL) === $state),
        100,
        100,
      )
      ->willReturn(TRUE);
    $wiki->expects(self::never())->method('addPersonalNotices');
    $items = [];

    (new PersonalNoticeAdapterHooks(['lms' => $lms, 'wiki' => $wiki]))
      ->noticesAlter($items, $account, ['origem' => 'wiki', 'page_state' => $state]);

    self::assertTrue($state->hasNext());
  }

  /**
   * A page for a non-adapter origin does not trigger unrelated adapters.
   */
  public function testNonAdapterOriginPageSkipsOptionalAdapters(): void {
    $account = $this->createMock(AccountInterface::class);
    $state = new PersonalNoticePageState('comunicado', 0);
    $lms = $this->createMock(PersonalNoticeAdapterInterface::class);
    $lms->expects(self::never())->method('addPersonalNotices');
    $wiki = $this->createMock(PaginatedPersonalNoticeAdapterInterface::class);
    $wiki->expects(self::never())->method('addPersonalNotices');
    $wiki->expects(self::never())->method('addPersonalNoticesPage');
    $items = [];

    (new PersonalNoticeAdapterHooks(['lms' => $lms, 'wiki' => $wiki]))
      ->noticesAlter($items, $account, ['origem' => 'comunicado', 'page_state' => $state]);

    self::assertSame([], $items);
    self::assertFalse($state->hasNext());
  }

  /**
   * Dispatches list contributions and unread counts to enabled adapters only.
   */
  public function testOptionalAdaptersExtendListAndCount(): void {
    $account = $this->createMock(AccountInterface::class);
    $lms = $this->createMock(PersonalNoticeAdapterInterface::class);
    $lms->expects(self::once())
      ->method('addPersonalNotices')
      ->with(self::anything(), $account, ['origem' => 'wiki'])
      ->willReturnCallback(static function (array &$items): void {
        $items[] = 'lms';
      });
    $lms->expects(self::once())->method('unreadCount')->with(42)->willReturn(2);
    $wiki = $this->createMock(PersonalNoticeAdapterInterface::class);
    $wiki->expects(self::once())
      ->method('addPersonalNotices')
      ->with(self::anything(), $account, ['origem' => 'wiki'])
      ->willReturnCallback(static function (array &$items): void {
        $items[] = 'wiki';
      });
    $wiki->expects(self::once())->method('unreadCount')->with(42)->willReturn(3);
    $hooks = new PersonalNoticeAdapterHooks(['lms' => $lms, 'wiki' => $wiki]);
    $items = ['forum'];
    $count = 4;

    $hooks->noticesAlter($items, $account, ['origem' => 'wiki']);
    $hooks->unreadCountAlter($count, 42);

    self::assertSame(['forum', 'lms', 'wiki'], $items);
    self::assertSame(9, $count);
  }

  /**
   * A failing optional adapter cannot break other origins or leak partial rows.
   */
  public function testAdapterFailuresAreIsolatedAndLoggedWithoutUserData(): void {
    $account = $this->createMock(AccountInterface::class);
    $failed = $this->createMock(PersonalNoticeAdapterInterface::class);
    $failed->expects(self::once())
      ->method('addPersonalNotices')
      ->willReturnCallback(static function (array &$items): void {
        $items[] = 'partial';
        throw new \RuntimeException('Sensitive fixture content');
      });
    $failed->expects(self::once())
      ->method('unreadCount')
      ->with(42)
      ->willThrowException(new \RuntimeException('Sensitive fixture content'));
    $healthy = $this->createMock(PersonalNoticeAdapterInterface::class);
    $healthy->expects(self::once())
      ->method('addPersonalNotices')
      ->willReturnCallback(static function (array &$items): void {
        $items[] = 'healthy';
      });
    $healthy->expects(self::once())->method('unreadCount')->with(42)->willReturn(3);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::exactly(2))
      ->method('error')
      ->with(
        'Optional personal notice adapter failed during @operation with @exception.',
        self::callback(static function (array $context): bool {
          self::assertSame(\RuntimeException::class, $context['@exception']);
          self::assertSame(['@adapter', '@operation', '@exception'], array_keys($context));
          self::assertStringNotContainsString('Sensitive fixture content', serialize($context));
          return in_array($context['@operation'], ['list', 'count'], TRUE)
            && $context['@adapter'] !== '';
        }),
      );
    $hooks = new PersonalNoticeAdapterHooks(['lms' => $failed, 'wiki' => $healthy], $logger);
    $items = ['forum'];
    $count = 4;

    $hooks->noticesAlter($items, $account);
    $hooks->unreadCountAlter($count, 42);

    self::assertSame(['forum', 'healthy'], $items);
    self::assertSame(7, $count);
  }

  /**
   * An enabled adapter with no notices leaves central values intact.
   */
  public function testEmptyAdapterContribution(): void {
    $adapter = $this->createMock(PersonalNoticeAdapterInterface::class);
    $adapter->expects(self::once())->method('addPersonalNotices');
    $adapter->expects(self::once())->method('unreadCount')->with(42)->willReturn(0);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::never())->method('error');
    $hooks = new PersonalNoticeAdapterHooks(['lms' => $adapter], $logger);
    $items = ['forum'];
    $count = 4;

    $hooks->noticesAlter($items, $this->createMock(AccountInterface::class));
    $hooks->unreadCountAlter($count, 42);

    self::assertSame(['forum'], $items);
    self::assertSame(4, $count);
  }

  /**
   * A lazy adapter construction failure does not prevent other adapters.
   */
  public function testAdapterInitializationFailuresAreIsolated(): void {
    $healthy = $this->createMock(PersonalNoticeAdapterInterface::class);
    $healthy->expects(self::once())
      ->method('addPersonalNotices')
      ->willReturnCallback(static function (array &$items): void {
        $items[] = 'healthy';
      });
    $healthy->expects(self::once())->method('unreadCount')->with(42)->willReturn(3);
    $container = new class($healthy) implements ContainerInterface {

      public function __construct(private readonly PersonalNoticeAdapterInterface $healthy) {}

      /** {@inheritdoc} */
      public function get(string $id): mixed {
        if ($id === 'lms') {
          throw new \RuntimeException('Sensitive construction details');
        }
        return $id === 'wiki' ? $this->healthy : NULL;
      }

      /** {@inheritdoc} */
      public function has(string $id): bool {
        return in_array($id, ['lms', 'wiki'], TRUE);
      }

    };
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::exactly(2))
      ->method('error')
      ->with(
        'Optional personal notice adapter @adapter could not initialize during @operation with @exception.',
        self::callback(static function (array $context): bool {
          self::assertSame(\RuntimeException::class, $context['@exception']);
          self::assertSame('lms', $context['@adapter']);
          self::assertStringNotContainsString('Sensitive construction details', serialize($context));
          return in_array($context['@operation'], ['list', 'count'], TRUE);
        }),
      );
    $hooks = new PersonalNoticeAdapterHooks($container, $logger);
    $items = [];
    $count = 0;

    $hooks->noticesAlter($items, $this->createMock(AccountInterface::class));
    $hooks->unreadCountAlter($count, 42);

    self::assertSame(['healthy'], $items);
    self::assertSame(3, $count);
  }

  /**
   * Negative adapter counts are rejected without reducing the central count.
   */
  public function testNegativeAdapterCountIsIgnored(): void {
    $adapter = $this->createMock(PersonalNoticeAdapterInterface::class);
    $adapter->expects(self::once())->method('unreadCount')->with(42)->willReturn(-3);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('error')
      ->with(
        'Optional personal notice adapter failed during @operation with @exception.',
        self::callback(static function (array $context): bool {
          self::assertSame('count', $context['@operation']);
          self::assertSame(\UnexpectedValueException::class, $context['@exception']);
          return TRUE;
        }),
      );
    $hooks = new PersonalNoticeAdapterHooks(['lms' => $adapter], $logger);
    $count = 5;

    $hooks->unreadCountAlter($count, 42);

    self::assertSame(5, $count);
  }

}
