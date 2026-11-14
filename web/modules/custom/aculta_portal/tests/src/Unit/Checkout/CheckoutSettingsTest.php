<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Padrões e leitura de `aculta_portal.checkout_settings` (P-CART E).
 */
#[Group('aculta_portal')]
final class CheckoutSettingsTest extends UnitTestCase {

  /**
   * Cria o objeto de configurações com os valores informados.
   *
   * @param array $values
   *   Valores da configuração de checkout.
   *
   * @return \Drupal\aculta_portal\Checkout\CheckoutSettings
   *   Configurações de checkout.
   */
  private function settings(array $values): CheckoutSettings {
    return new CheckoutSettings($this->getConfigFactoryStub([CheckoutSettings::CONFIG => $values]));
  }

  /**
   * Confirma que, sem configuração, valem os padrões anteriores.
   */
  public function testSemConfigValemOsPadroesAnteriores(): void {
    $settings = $this->settings([]);
    $this->assertTrue($settings->commonCheckoutEnabled());
    $this->assertSame('default', $settings->shopOrderType());
    $this->assertSame('curso', $settings->lmsOrderType());
    $this->assertSame(['curso'], $settings->lmsOrderTypes());
  }

  /**
   * Confirma que valores vazios ou inválidos caem no padrão.
   */
  public function testValoresVaziosOuInvalidosCaemNoPadrao(): void {
    $settings = $this->settings(['shop_order_type' => '', 'lms_order_type' => NULL]);
    $this->assertSame('default', $settings->shopOrderType());
    $this->assertSame('curso', $settings->lmsOrderType());
  }

  /**
   * Confirma que o tipo LMS configurado vem primeiro e conserva o curso histórico.
   */
  public function testLmsConfiguradoVemPrimeiroESempreMantemOCursoHistorico(): void {
    $this->assertSame(['aula', 'curso'], $this->settings(['lms_order_type' => 'aula'])->lmsOrderTypes());
  }

  /**
   * Confirma que a chave global é lida como booleano.
   */
  public function testChaveGlobalLidaComoBooleano(): void {
    $this->assertFalse($this->settings(['common_checkout_enabled' => FALSE])->commonCheckoutEnabled());
    $this->assertTrue($this->settings(['common_checkout_enabled' => TRUE])->commonCheckoutEnabled());
  }

}
