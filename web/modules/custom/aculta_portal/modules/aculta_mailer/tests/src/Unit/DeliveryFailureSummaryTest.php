<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\DeliveryFailureSummary;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

/**
 * Persisted failure summaries contain only safe exception classes and codes.
 */
#[Group('aculta_mailer')]
final class DeliveryFailureSummaryTest extends UnitTestCase {

  /**
   * Includes only the validated class and SMTP code.
   */
  public function testSummaryIncludesValidatedExceptionClassAndSmtpCode(): void {
    $summary = DeliveryFailureSummary::create(UnexpectedResponseException::class, 550);

    $this->assertSame('Symfony\\Component\\Mailer\\Exception\\UnexpectedResponseException (SMTP 550)', $summary);
  }

  /**
   * Rejects arbitrary text and out-of-range SMTP codes.
   */
  public function testSummaryDoesNotAcceptArbitraryTextOrInvalidSmtpCodes(): void {
    $summary = DeliveryFailureSummary::create('private-person@example.invalid raw-secret', 999);

    $this->assertSame('UnknownTransportFailure', $summary);
    $this->assertStringNotContainsString('private-person@example.invalid', $summary);
    $this->assertStringNotContainsString('raw-secret', $summary);
  }

}
