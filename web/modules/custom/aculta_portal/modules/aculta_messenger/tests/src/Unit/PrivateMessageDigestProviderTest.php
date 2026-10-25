<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Aviso\PrivateMessageDigestProvider;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\message\MessageInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests weekly private-message digest eligibility. */
#[CoversClass(PrivateMessageDigestProvider::class)]
#[Group('aculta_messenger')]
final class PrivateMessageDigestProviderTest extends UnitTestCase {

  /**
   * The admin anchor and per-user seven-day window gate discovery.
   */
  public function testPendingRecipientMustBePastAnchorAndSevenDays(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('getOwnerId')->willReturn(9);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($this->pendingQuery([41]));
    $messageStorage->method('loadMultiple')->with([41])->willReturn([41 => $message]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($messageStorage);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(9, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES)
      ->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $configFactory = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => ['private_message_digest_anchor' => '2026-10-26'],
    ]);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->with('aculta_messenger', 9, 'private_message_digest_last_sent')
      ->willReturn(strtotime('2026-10-27 00:00:00 UTC'));
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnOnConsecutiveCalls(
      (int) strtotime('2026-11-02 23:59:59 UTC'),
      (int) strtotime('2026-11-03 00:00:00 UTC'),
    );
    $provider = new PrivateMessageDigestProvider(
      $entities,
      $this->createMock(PrivateMessageServiceInterface::class),
      $preferences,
      $configFactory,
      $userData,
      $time,
    );

    self::assertSame([], $provider->pendingRecipientIds());
    self::assertSame([9], $provider->pendingRecipientIds());
  }

  /**
   * Builds the idempotent pending-message query.
   */
  private function pendingQuery(array $ids): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($ids);
    return $query;
  }

}
