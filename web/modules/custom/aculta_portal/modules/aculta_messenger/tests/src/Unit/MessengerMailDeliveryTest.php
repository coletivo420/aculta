<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\aculta_messenger\Service\MessengerMailDelivery;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests stable and migration-safe direct email correlations.
 */
#[CoversClass(MessengerMailDelivery::class)]
#[Group('aculta_messenger')]
final class MessengerMailDeliveryTest extends UnitTestCase {

  /**
   * Retries use the same idempotent correlation for one logical delivery.
   */
  public function testUsesStableUuidV5Correlation(): void {
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::exactly(2))
      ->method('wasAcceptedCorrelation')
      ->with('01234567-89ab-4cde-8fab-0123456789ab', 'forum_notice', 'member@example.test')
      ->willReturn(FALSE);
    $correlations = [];
    $mailer->expects(self::exactly(2))
      ->method('deliver')
      ->willReturnCallback(static function (MailMessage $message) use (&$correlations): SendResult {
        $correlations[] = $message->correlationId;
        return SendResult::sent();
      });

    for ($attempt = 0; $attempt < 2; $attempt++) {
      self::assertTrue(MessengerMailDelivery::deliver(
        $mailer,
        'forum_notice',
        'member@example.test',
        'pt-br',
        ['summary' => 'Novo aviso'],
        'forum:event:17',
        '01234567-89ab-4cde-8fab-0123456789ab',
      )->sent);
    }

    self::assertSame([
      MessageIdFactory::correlationIdFor('aculta_messenger:forum:event:17'),
      MessageIdFactory::correlationIdFor('aculta_messenger:forum:event:17'),
    ], $correlations);
    self::assertSame('5', $correlations[0][14]);
  }

  /**
   * An accepted legacy correlation is reconciled without another transport.
   */
  public function testReconcilesAcceptedLegacyCorrelation(): void {
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())
      ->method('wasAcceptedCorrelation')
      ->with('01234567-89ab-4cde-8fab-0123456789ab', 'forum_notice', 'member@example.test')
      ->willReturn(TRUE);
    $mailer->expects(self::never())->method('deliver');

    $result = MessengerMailDelivery::deliver(
      $mailer,
      'forum_notice',
      'member@example.test',
      'pt-br',
      [],
      'forum:event:17',
      '01234567-89ab-4cde-8fab-0123456789ab',
    );

    self::assertTrue($result->sent);
  }

  /**
   * Empty source identities cannot become a shared idempotency key.
   */
  public function testRejectsEmptyIdempotencyKey(): void {
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('wasAcceptedCorrelation');
    $mailer->expects(self::never())->method('deliver');

    $this->expectException(\InvalidArgumentException::class);
    MessengerMailDelivery::deliver($mailer, 'forum_notice', 'member@example.test', 'pt-br', [], '');
  }

}
