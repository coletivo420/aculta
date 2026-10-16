<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\Quarantine\QuarantineRules;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Prazo da quarentena: 30 dias a partir do envio; vencida só no próprio instante do vencimento ou depois.
 */
#[Group('aculta_image')]
class QuarantineRulesTest extends UnitTestCase {

  public function testPrazoDeTrintaDias(): void {
    $this->assertSame(1000 + 30 * 86400, QuarantineRules::vencimento(1000));
  }

  public function testVencimentoNoInstante(): void {
    $vence = QuarantineRules::vencimento(1000);
    $this->assertFalse(QuarantineRules::vencida($vence, $vence - 1));
    $this->assertTrue(QuarantineRules::vencida($vence, $vence));
    $this->assertTrue(QuarantineRules::vencida($vence, $vence + 1));
  }

}
