<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Xapi\XapiTokenSettings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the effective token validade read from configuration (ADR-041).
 */
#[CoversClass(XapiTokenSettings::class)]
final class XapiTokenSettingsTest extends UnitTestCase {

  /**
   * Valor ausente cai no padrão de 30 minutos.
   */
  public function testMissingValueUsesDefault(): void {
    $this->assertSame(XapiTokenSettings::DEFAULT_TTL, XapiTokenSettings::ttlFrom(NULL));
  }

  /**
   * Valor inteiro válido, ou texto numérico vindo do formulário, é usado.
   */
  public function testValidValueIsUsed(): void {
    $this->assertSame(3600, XapiTokenSettings::ttlFrom(3600));
    $this->assertSame(3600, XapiTokenSettings::ttlFrom('3600'));
  }

  /**
   * Abaixo do mínimo cai no padrão, e não em zero.
   */
  public function testBelowMinimumUsesDefault(): void {
    $this->assertSame(XapiTokenSettings::DEFAULT_TTL, XapiTokenSettings::ttlFrom(XapiTokenSettings::MIN_TTL - 1));
  }

  /**
   * Acima do teto do token cai no padrão, nunca acima do teto.
   */
  public function testAboveMaximumUsesDefault(): void {
    $this->assertSame(XapiTokenSettings::DEFAULT_TTL, XapiTokenSettings::ttlFrom(XapiTokenSettings::MAX_TTL + 1));
  }

  /**
   * Texto não numérico ou número negativo cai no padrão.
   */
  public function testNonNumericUsesDefault(): void {
    $this->assertSame(XapiTokenSettings::DEFAULT_TTL, XapiTokenSettings::ttlFrom('1h'));
    $this->assertSame(XapiTokenSettings::DEFAULT_TTL, XapiTokenSettings::ttlFrom(-5));
  }

}
