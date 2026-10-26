<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Quote\QuoteOption;
use Drupal\aculta_commerce_melhorenvio\Quote\QuoteRateFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Conversão de opções cotadas em tarifas do motor ACULTA.
 */
#[Group('aculta_commerce_melhorenvio')]
final class QuoteRateFactoryTest extends UnitTestCase {

  /**
   * Opções de exemplo.
   *
   * @return list<\Drupal\aculta_commerce_melhorenvio\Quote\QuoteOption>
   *   PAC e SEDEX.
   */
  private static function options(): array {
    return [
      new QuoteOption('1', 'PAC', 'Correios', '28.33', 7),
      new QuoteOption('2', 'SEDEX', 'Correios', '57.23', NULL),
    ];
  }

  /**
   * Cada opção vira uma tarifa em reais, com o serviço do Melhor Envio.
   */
  public function testRatesInBrl(): void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $rates = (new QuoteRateFactory())->rates(self::options(), 'me', []);
    self::assertCount(2, $rates);
    self::assertSame('Drupal\\aculta_commerce\\Shipping\\ShippingRate', get_class($rates[0]));
    self::assertSame('me--1', $rates[0]->getId());
    self::assertSame('1', $rates[0]->getService()->getId());
    self::assertSame('PAC (Correios)', (string) $rates[0]->getService()->getLabel());
    self::assertSame('28.33', $rates[0]->getAmount()->getNumber());
    self::assertSame('BRL', $rates[0]->getAmount()->getCurrencyCode());
    self::assertSame('Entrega em até 7 dias úteis', (string) $rates[0]->getDescription());
    self::assertSame('', (string) $rates[1]->getDescription());
    self::assertSame('1', $rates[0]->getData('melhorenvio_service_id'));
  }

  /**
   * Só os serviços habilitados no método viram tarifa.
   */
  public function testOnlyAllowedServices(): void {
    $rates = (new QuoteRateFactory())->rates(self::options(), 'me', ['2']);
    self::assertCount(1, $rates);
    self::assertSame('2', $rates[0]->getService()->getId());
  }

}
