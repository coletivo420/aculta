<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Friendship\FriendshipNoticeManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Container\ContainerInterface;

/**
 * Tests site delivery and read state for friendship notices.
 */
#[CoversClass(FriendshipNoticeManager::class)]
#[Group('aculta_messenger')]
final class FriendshipNoticeManagerTest extends UnitTestCase {

  /**
   * An unread friendship event enters the common notice list by owner only.
   */
  public function testAddsOwnedUnreadTransitionToPersonalNoticeCenter(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isAuthenticated')->willReturn(TRUE);
    $notice = $this->notice(81, 27, 'accepted', 0);
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->with(0, 20)->willReturnSelf();
    $query->method('execute')->willReturn([81]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with([81])->willReturn([81 => $notice]);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('message')->willReturn($storage);

    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')->willReturnCallback(
      static fn(string $route, array $parameters = [], array $options = [], bool $collectMetadata = FALSE): string|GeneratedUrl =>
        $collectMetadata
          ? (new GeneratedUrl())->setGeneratedUrl('/avisos-mensagens/mensagens')
          : '/avisos-mensagens/mensagens',
    );
    $oldContainer = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);
    try {
      $manager = new FriendshipNoticeManager($entityTypes);
      $manager->setStringTranslation($this->getStringTranslationStub());
      $items = [];

      self::assertFalse($manager->addPersonalNoticesPage(
        $items,
        $account,
        ['origem' => 'amizade', 'leitura' => 'unread'],
        10,
        0,
      ));
      self::assertCount(1, $items);
      self::assertSame('amizade', $items[0]['#attributes']['data-aculta-notice-origin']);
      self::assertSame('unread', $items[0]['#attributes']['data-aculta-notice-read']);
      self::assertSame('81', $items[0]['#attributes']['data-aculta-notice-message-id']);
      self::assertSame('/avisos-mensagens/mensagens', (string) $items[0]['link']['#element_attributes']->offsetGet('href'));
      self::assertSame('Pedido de amizade aceito', (string) $items[0]['link']['#value']['title']['#value']);
    }
    finally {
      if ($oldContainer instanceof ContainerInterface) {
        \Drupal::setContainer($oldContainer);
      }
      else {
        \Drupal::unsetContainer();
      }
    }
  }

  /**
   * Read-state updates require an owned friendship event and supported bundle.
   */
  public function testReadStateRequiresOwnerAndFriendshipBundle(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isAuthenticated')->willReturn(TRUE);
    $manager = new FriendshipNoticeManager($this->createMock(EntityTypeManagerInterface::class));
    $manager->setStringTranslation($this->getStringTranslationStub());
    $owned = $this->notice(81, 27, 'requested', 0);
    $owned->expects(self::once())->method('set')->with('field_aculta_friendship_read', 1)->willReturnSelf();
    $owned->expects(self::once())->method('save')->willReturn(1);
    self::assertTrue($manager->setRead($owned, $account, TRUE));

    $otherOwner = $this->notice(82, 84, 'requested', 0);
    $otherOwner->expects(self::never())->method('set');
    self::assertFalse($manager->setRead($otherOwner, $account, TRUE));

    $unsupported = $this->notice(83, 27, 'declined', 0);
    $unsupported->expects(self::never())->method('set');
    self::assertFalse($manager->setRead($unsupported, $account, TRUE));
  }

  /**
   * The unread count is scoped to active owner, event and read state.
   */
  public function testUnreadCountUsesRecipientScopedMessageQuery(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('load')->with(27)->willReturn($account);
    $query = $this->createMock(QueryInterface::class);
    $query->expects(self::once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $expectedConditions = [
      ['template', FriendshipNoticeManager::TEMPLATE, NULL],
      ['uid', 27, NULL],
      ['field_aculta_friendship_event', ['requested', 'accepted'], 'IN'],
      ['field_aculta_friendship_read', 0, NULL],
    ];
    $conditions = [];
    $query->expects(self::exactly(4))->method('condition')->willReturnCallback(
      static function (string $field, mixed $value, ?string $operator = NULL) use ($query, &$conditions, $expectedConditions): QueryInterface {
        $conditions[] = [$field, $value, $operator];
        self::assertSame($expectedConditions[count($conditions) - 1], end($conditions));
        return $query;
      },
    );
    $query->expects(self::once())->method('count')->willReturnSelf();
    $query->expects(self::once())->method('execute')->willReturn(3);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->expects(self::once())->method('getQuery')->willReturn($query);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['user', $userStorage],
      ['message', $messageStorage],
    ]);

    self::assertSame(3, (new FriendshipNoticeManager($entities))->unreadCount(27));
    self::assertSame($expectedConditions, $conditions);
  }

  /**
   * Creates a persisted friendship notice.
   */
  private function notice(int $id, int $uid, string $event, int $read): MessageInterface {
    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn((string) $id);
    $message->method('bundle')->willReturn(FriendshipNoticeManager::TEMPLATE);
    $message->method('getOwnerId')->willReturn($uid);
    $message->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => match ($field) {
      'field_aculta_friendship_event' => $this->fieldValue($event),
      'field_aculta_friendship_read' => $this->fieldValue($read),
      default => throw new \InvalidArgumentException($field),
    });
    return $message;
  }

  /**
   * Creates a field list with one scalar value.
   */
  private function fieldValue(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

}
