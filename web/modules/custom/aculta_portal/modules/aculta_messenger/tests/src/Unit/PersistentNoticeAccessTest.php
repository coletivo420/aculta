<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\site_alert\GetAlertsInterface;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Hook\MessengerBarHooks;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_messenger\Service\MessengerCounts;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects the audience and validity rules for persistent notices.
 *
 * @group aculta_messenger
 */
#[Group('aculta_messenger')]
final class PersistentNoticeAccessTest extends UnitTestCase {

  public function testAudienceIsRoleScopedAndCacheVariesByAccount(): void {
    $node = $this->notice(['authenticated', 'moderator']);
    $account = $this->account(['authenticated']);
    $result = $this->hooks(1000)->nodeAccess($node, 'view', $account);

    $this->assertTrue($result->isAllowed());
    $this->assertContains('user', $result->getCacheContexts());
  }

  public function testNonMatchingRoleFailsClosed(): void {
    $node = $this->notice(['moderator']);
    $result = $this->hooks(1000)->nodeAccess($node, 'view', $this->account(['authenticated']));

    $this->assertFalse($result->isAllowed());
    $this->assertTrue($result->isForbidden());
    $this->assertContains('user', $result->getCacheContexts());
  }

  public function testUnpublishedNoticeFailsClosed(): void {
    $node = $this->notice(['authenticated'], FALSE);
    $result = $this->hooks(1000)->nodeAccess($node, 'view', $this->account(['authenticated']));

    $this->assertTrue($result->isForbidden());
  }

  public function testNoticeIsUnavailableBeforeItsStartAndCacheExpiresAtStart(): void {
    $now = time();
    $start = gmdate('Y-m-d\TH:i:s', $now + 200);
    $node = $this->notice(['authenticated'], TRUE, ['field_aculta_notice_start' => $start]);
    $result = $this->hooks($now)->nodeAccess($node, 'view', $this->account(['authenticated']));

    $this->assertTrue($result->isForbidden());
    $this->assertSame(200, $result->getCacheMaxAge());
  }

  public function testNoticeIsUnavailableAtItsEnd(): void {
    $now = time();
    $end = gmdate('Y-m-d\TH:i:s', $now - 200);
    $node = $this->notice(['authenticated'], TRUE, ['field_aculta_notice_end' => $end]);
    $result = $this->hooks($now)->nodeAccess($node, 'view', $this->account(['authenticated']));

    $this->assertTrue($result->isForbidden());
  }

  private function hooks(int $requestTime): MessengerBarHooks {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($requestTime);
    return new MessengerBarHooks(
      $this->createMock(AccountInterface::class),
      new UnreadThreadCounter($this->createMock(AccountInterface::class), $this->createMock(PrivateMessageServiceInterface::class)),
      $this->createMock(GetAlertsInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(RendererInterface::class),
      $time,
      $this->createMock(FlagServiceInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      new MessengerCounts($this->createMock(EntityTypeManagerInterface::class), $this->createMock(FlagServiceInterface::class), $this->createMock(GetAlertsInterface::class)),
      new AvisoService($this->createMock(EntityTypeManagerInterface::class), $this->createMock(FlagServiceInterface::class)),
      new DomainPurposeManager(
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
    );
  }

  private function account(array $roles): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('getRoles')->willReturn($roles);
    $account->method('hasPermission')->willReturn(FALSE);
    return $account;
  }

  private function notice(array $roles, bool $published = TRUE, array $dates = []): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_notice');
    $node->method('isPublished')->willReturn($published);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn(['node:123']);
    $node->method('getCacheMaxAge')->willReturn(-1);
    $node->method('hasField')->willReturnCallback(static function (string $field) use ($dates): bool {
      return $field === 'field_aculta_notice_roles' || array_key_exists($field, $dates);
    });
    $node->method('get')->willReturnCallback(function (string $field) use ($roles, $dates): FieldItemListInterface {
      $itemList = $this->createMock(FieldItemListInterface::class);
      if ($field === 'field_aculta_notice_roles') {
        $values = array_map(static fn (string $role): array => ['value' => $role], $roles);
      }
      elseif (isset($dates[$field])) {
        $values = [['value' => $dates[$field]]];
      }
      else {
        $values = [];
      }
      $itemList->method('isEmpty')->willReturn($values === []);
      $itemList->method('getValue')->willReturn($values);
      $itemList->method('__get')->with('value')->willReturn($values[0]['value'] ?? NULL);
      return $itemList;
    });
    return $node;
  }

}
