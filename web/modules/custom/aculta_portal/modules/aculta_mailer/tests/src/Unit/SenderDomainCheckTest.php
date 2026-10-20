<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Diagnostics\SenderDomainCheck;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Classificação de SPF e DMARC do domínio remetente.
 */
#[Group('aculta_mailer')]
final class SenderDomainCheckTest extends UnitTestCase {

  /**
   * SPF: ausente, único e repetido (RFC 7208).
   */
  public function testSpfClassification(): void {
    $this->assertSame(SenderDomainCheck::SPF_MISSING, SenderDomainCheck::classifySpf([]));
    $this->assertSame(SenderDomainCheck::SPF_MISSING, SenderDomainCheck::classifySpf(['google-site-verification=abc']));
    $this->assertSame(SenderDomainCheck::SPF_OK, SenderDomainCheck::classifySpf([
      'brevo-code:123',
      'v=spf1 include:_spf.mail.hostinger.com ~all',
    ]));
    $this->assertSame(SenderDomainCheck::SPF_MULTIPLE, SenderDomainCheck::classifySpf(['v=spf1 -all', 'v=spf1 ~all']));
  }

  /**
   * DMARC: política por tag p=, registro sem política e ausência.
   */
  public function testDmarcClassification(): void {
    $this->assertSame(SenderDomainCheck::DMARC_MISSING, SenderDomainCheck::classifyDmarc([]));
    $this->assertSame('none', SenderDomainCheck::classifyDmarc(['v=DMARC1; p=none; rua=mailto:dmarc@example.invalid']));
    $this->assertSame('quarantine', SenderDomainCheck::classifyDmarc(['v=DMARC1; p=quarantine']));
    $this->assertSame('reject', SenderDomainCheck::classifyDmarc(['v=DMARC1;p=reject;pct=100']));
    $this->assertSame(SenderDomainCheck::DMARC_MISSING, SenderDomainCheck::classifyDmarc(['v=DMARC1; rua=mailto:dmarc@example.invalid']));
    $this->assertSame(SenderDomainCheck::DMARC_MISSING, SenderDomainCheck::classifyDmarc(['v=spf1 -all']));
  }

}
