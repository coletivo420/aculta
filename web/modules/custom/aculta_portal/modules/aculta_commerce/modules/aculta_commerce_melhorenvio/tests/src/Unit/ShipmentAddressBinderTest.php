<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\aculta_commerce_melhorenvio\Address\MelhorEnvioAddressMapper;
use Drupal\aculta_commerce_melhorenvio\Address\ShipmentAddressBinder;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Par origem/destino: origem de configuração, destino do perfil de envio, mesma regra para cotação e contratação.
 */
#[CoversClass(ShipmentAddressBinder::class)]
#[Group('aculta_commerce_melhorenvio')]
class ShipmentAddressBinderTest extends UnitTestCase {

  private const ORIGIN = [
    'country_code' => 'BR',
    'postal_code' => '04538-132',
    'address_line1' => 'Rua da Loja, 200',
    'address_line2' => '',
    'dependent_locality' => 'Itaim Bibi',
    'locality' => 'São Paulo',
    'administrative_area' => 'SP',
  ];

  private const DESTINATION = [
    'country_code' => 'BR',
    'postal_code' => '01310-100',
    'address_line1' => 'Avenida Paulista, 1000',
    'address_line2' => 'Apto 12',
    'dependent_locality' => 'Bela Vista',
    'locality' => 'São Paulo',
    'administrative_area' => 'SP',
  ];

  private function binder(?array $origin): ShipmentAddressBinder {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('origin')->willReturn($origin);
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->with(ShipmentAddressBinder::SETTINGS)->willReturn($config);

    $cep = new class implements CepLookupInterface {

      public function normalize(string $cep): ?string {
        $digits = preg_replace('/\D+/', '', $cep) ?? '';
        return strlen($digits) === 8 ? $digits : NULL;
      }

      public function lookup(string $cep): ?array {
        throw new \LogicException('O binder não pode consultar CEP.');
      }

    };
    return new ShipmentAddressBinder(new MelhorEnvioAddressMapper($cep), $configs);
  }

  public function testOriginComesFromConfigAndDestinationFromProfile(): void {
    $pair = $this->binder(self::ORIGIN)->bind(self::DESTINATION, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertTrue($pair->isValid());
    $this->assertSame('04538132', $pair->from->postalCode);
    $this->assertSame('1000', $pair->to->number);
    $this->assertSame('Apto 12', $pair->to->complement);
  }

  public function testQuoteAndShipmentUseTheSameDestinationPostalCode(): void {
    $binder = $this->binder(self::ORIGIN);
    $quote = $binder->bind(self::DESTINATION, MelhorEnvioAddressMapper::PURPOSE_QUOTE);
    $shipment = $binder->bind(self::DESTINATION, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertSame($quote->to->postalCode, $shipment->to->postalCode);
    $this->assertSame($quote->from->postalCode, $shipment->from->postalCode);
  }

  public function testEmptyOriginConfigBlocksOperationWithoutFallback(): void {
    $pair = $this->binder(['postal_code' => '', 'address_line1' => ''])->bind(self::DESTINATION, MelhorEnvioAddressMapper::PURPOSE_QUOTE);

    $this->assertFalse($pair->isValid());
    $this->assertContains(MelhorEnvioAddressMapper::ERR_POSTAL_CODE, $pair->errors()['origin']);
    $this->assertSame([], $pair->errors()['destination']);
  }

  public function testMissingDestinationProfileIsReportedOnDestinationSide(): void {
    $pair = $this->binder(self::ORIGIN)->bind(NULL, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertFalse($pair->isValid());
    $this->assertSame([MelhorEnvioAddressMapper::ERR_MISSING], $pair->errors()['destination']);
    $this->assertSame([], $pair->errors()['origin']);
  }

  public function testSnDestinationBlocksShipmentButNotQuote(): void {
    $sn = ['address_line1' => 'Rua Exemplo, S/N'] + self::DESTINATION;
    $binder = $this->binder(self::ORIGIN);

    $this->assertSame([MelhorEnvioAddressMapper::ERR_SN_NOT_ACCEPTED], $binder->bind($sn, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT)->errors()['destination']);
    $this->assertTrue($binder->bind($sn, MelhorEnvioAddressMapper::PURPOSE_QUOTE)->isValid());
  }

}
