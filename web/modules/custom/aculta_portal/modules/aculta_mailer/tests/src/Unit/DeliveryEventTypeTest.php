<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Webhook\DeliveryEventType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cada evento de entrega corresponde ao estado do registro esperado.
 */
#[CoversClass(DeliveryEventType::class)]
#[Group('aculta_mailer')]
final class DeliveryEventTypeTest extends UnitTestCase {

  /**
   * Mapeamento evento → estado.
   */
  public function testEventsMapToStatuses(): void {
    $this->assertSame(MailStatus::Delivered, DeliveryEventType::Delivered->status());
    $this->assertSame(MailStatus::Deferred, DeliveryEventType::Deferred->status());
    $this->assertSame(MailStatus::Bounced, DeliveryEventType::Bounced->status());
    $this->assertSame(MailStatus::Complained, DeliveryEventType::Complained->status());
  }

}
