<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\DeliveryPlanner;
use Drupal\aculta_mailer\Delivery\RetrySchedule;
use Drupal\aculta_mailer\Delivery\TransportFailover;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Decisão combinada de retry e failover após uma falha.
 */
#[CoversClass(DeliveryPlanner::class)]
#[Group('aculta_mailer')]
final class DeliveryPlannerTest extends UnitTestCase {

  /**
   * Cria o planner com padrão Hostinger e contingência SMTP2GO.
   */
  private function planner(): DeliveryPlanner {
    return new DeliveryPlanner(new RetrySchedule(), new TransportFailover('hostinger', 'smtp2go'));
  }

  /**
   * Erro temporário na primeira tentativa reagenda em 1 min pelo fallback.
   */
  public function testTemporaryFailureDefersOnFallbackAfterOneMinute(): void {
    $decision = $this->planner()->afterFailure(1, 'hostinger', DeliveryError::Temporary);

    $this->assertSame(MailStatus::Deferred, $decision->status);
    $this->assertSame('smtp2go', $decision->transport);
    $this->assertSame(60, $decision->delaySeconds);
    $this->assertSame(2, $decision->nextAttempt);
    $this->assertTrue($decision->shouldRetry());
  }

  /**
   * Erro permanente encerra sem retry e sem troca de transporte.
   */
  public function testPermanentFailureFailsImmediately(): void {
    $decision = $this->planner()->afterFailure(1, 'hostinger', DeliveryError::Permanent);

    $this->assertSame(MailStatus::Failed, $decision->status);
    $this->assertNull($decision->transport);
    $this->assertNull($decision->delaySeconds);
    $this->assertFalse($decision->shouldRetry());
  }

  /**
   * Após a última tentativa, erro temporário encerra em failed.
   */
  public function testExhaustedAttemptsFail(): void {
    $decision = $this->planner()->afterFailure(RetrySchedule::MAX_ATTEMPTS, 'smtp2go', DeliveryError::Temporary);

    $this->assertSame(MailStatus::Failed, $decision->status);
    $this->assertFalse($decision->shouldRetry());
  }

  /**
   * Falha no fallback reagenda no mesmo transporte, com o atraso da tentativa.
   */
  public function testFallbackFailureRetriesSameTransport(): void {
    $decision = $this->planner()->afterFailure(2, 'smtp2go', DeliveryError::Temporary);

    $this->assertSame(MailStatus::Deferred, $decision->status);
    $this->assertSame('smtp2go', $decision->transport);
    $this->assertSame(300, $decision->delaySeconds);
  }

}
