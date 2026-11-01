<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\aculta_commerce_melhorenvio\Address\MelhorEnvioAddressMapper;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Mapeamento do endereço ACULTA para o Melhor Envio: número, S/N, complemento, CEP e campos por operação.
 */
#[CoversClass(MelhorEnvioAddressMapper::class)]
#[Group('aculta_commerce_melhorenvio')]
class MelhorEnvioAddressMapperTest extends UnitTestCase {

  /** Endereço completo gravado como o formulário do ACULTA grava (join: "rua, número"). */
  private const FULL = [
    'country_code' => 'BR',
    'postal_code' => '01310-100',
    'address_line1' => 'Avenida Paulista, 1000',
    'address_line2' => 'Apto 12',
    'dependent_locality' => 'Bela Vista',
    'locality' => 'São Paulo',
    'administrative_area' => 'SP',
  ];

  private function mapper(): MelhorEnvioAddressMapper {
    // Cep falso: normaliza como o serviço real, e falha se o mapper tentar consultar (sem HTTP, sem lookup).
    return new MelhorEnvioAddressMapper(new class implements CepLookupInterface {

      public function normalize(string $cep): ?string {
        $digits = preg_replace('/\D+/', '', $cep) ?? '';
        return strlen($digits) === 8 ? $digits : NULL;
      }

      public function lookup(string $cep): ?array {
        throw new \LogicException('O mapper não pode consultar CEP.');
      }

    });
  }

  public function testCompleteAddressMapsToShipmentPayload(): void {
    $result = $this->mapper()->map(self::FULL, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertTrue($result->isValid());
    $this->assertSame([
      'postal_code' => '01310100',
      'address' => 'Avenida Paulista',
      'number' => '1000',
      'complement' => 'Apto 12',
      'district' => 'Bela Vista',
      'city' => 'São Paulo',
      'state_abbr' => 'SP',
      'country_id' => 'BR',
    ], $result->toShipmentArray());
  }

  public function testComplementIsOptional(): void {
    $address = ['address_line2' => ''] + self::FULL;
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertTrue($result->isValid());
    $this->assertSame('', $result->complement);
  }

  public function testSnBlocksShipmentWithoutConvertingToZero(): void {
    $address = ['address_line1' => 'Rua Exemplo, S/N'] + self::FULL;
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertFalse($result->isValid());
    $this->assertSame([MelhorEnvioAddressMapper::ERR_SN_NOT_ACCEPTED], $result->errors);
    $this->assertNotSame('0', $result->number);
    $this->assertSame('', $result->number);
  }

  public function testSnIsAcceptedForQuote(): void {
    $address = ['address_line1' => 'Rua Exemplo, S/N'] + self::FULL;
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_QUOTE);

    $this->assertTrue($result->isValid());
    $this->assertSame(['postal_code' => '01310100'], $result->toQuoteArray());
  }

  public function testTenDigitNumberIsAccepted(): void {
    $address = ['address_line1' => 'Rua A, 1234567890'] + self::FULL;
    $this->assertTrue($this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT)->isValid());
  }

  public function testElevenDigitNumberIsInvalid(): void {
    $address = ['address_line1' => 'Rua A, 12345678901'] + self::FULL;
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertSame([MelhorEnvioAddressMapper::ERR_NUMBER_INVALID], $result->errors);
  }

  public function testNonDigitNumberIsInvalidNotFalsified(): void {
    $address = ['address_line1' => 'Rua A, Bloco B'] + self::FULL;
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertSame([MelhorEnvioAddressMapper::ERR_NUMBER_INVALID], $result->errors);
  }

  public function testMissingNumberWithoutSnIsError(): void {
    $address = ['address_line1' => 'Avenida Paulista'] + self::FULL;
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertSame([MelhorEnvioAddressMapper::ERR_NUMBER_MISSING], $result->errors);
  }

  public function testStreetWithCommaKeepsFullStreetAndLastNumber(): void {
    $address = ['address_line1' => 'Rua Um, Centro, 45'] + self::FULL;
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertTrue($result->isValid());
    $this->assertSame('Rua Um, Centro', $result->street);
    $this->assertSame('45', $result->number);
  }

  public function testForeignCountryIsRejectedForBothPurposes(): void {
    $address = ['country_code' => 'US'] + self::FULL;
    $mapper = $this->mapper();

    $this->assertSame([MelhorEnvioAddressMapper::ERR_COUNTRY], $mapper->map($address, MelhorEnvioAddressMapper::PURPOSE_QUOTE)->errors);
    $this->assertSame([MelhorEnvioAddressMapper::ERR_COUNTRY], $mapper->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT)->errors);
  }

  public function testMaskedPostalCodeIsNormalized(): void {
    $result = $this->mapper()->map(self::FULL, MelhorEnvioAddressMapper::PURPOSE_QUOTE);
    $this->assertSame('01310100', $result->postalCode);
  }

  public function testInvalidPostalCodeIsRejected(): void {
    $address = ['postal_code' => '0131'] + self::FULL;
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_QUOTE);

    $this->assertSame([MelhorEnvioAddressMapper::ERR_POSTAL_CODE], $result->errors);
  }

  public function testMissingDistrictBlocksShipmentOnly(): void {
    $address = ['dependent_locality' => ''] + self::FULL;
    $mapper = $this->mapper();

    $this->assertSame([MelhorEnvioAddressMapper::ERR_DISTRICT], $mapper->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT)->errors);
    $this->assertTrue($mapper->map($address, MelhorEnvioAddressMapper::PURPOSE_QUOTE)->isValid());
  }

  public function testUnresolvedAddressWithoutFieldsIsIncomplete(): void {
    // CEP válido que a consulta não resolveu: logradouro, bairro, cidade e UF vazios.
    $address = [
      'country_code' => 'BR',
      'postal_code' => '99999999',
      'address_line1' => '',
      'dependent_locality' => '',
      'locality' => '',
      'administrative_area' => '',
    ];
    $result = $this->mapper()->map($address, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertFalse($result->isValid());
    $this->assertContains(MelhorEnvioAddressMapper::ERR_STREET, $result->errors);
    $this->assertContains(MelhorEnvioAddressMapper::ERR_CITY, $result->errors);
    $this->assertContains(MelhorEnvioAddressMapper::ERR_STATE, $result->errors);
  }

  public function testMissingProfileIsError(): void {
    $result = $this->mapper()->map(NULL, MelhorEnvioAddressMapper::PURPOSE_QUOTE);

    $this->assertSame([MelhorEnvioAddressMapper::ERR_MISSING], $result->errors);
  }

  public function testUnknownPurposeIsError(): void {
    $result = $this->mapper()->map(self::FULL, 'label-purchase');

    $this->assertSame([MelhorEnvioAddressMapper::ERR_PURPOSE], $result->errors);
  }

  public function testQuoteAndShipmentShareTheSamePostalCode(): void {
    $mapper = $this->mapper();
    $quote = $mapper->map(self::FULL, MelhorEnvioAddressMapper::PURPOSE_QUOTE);
    $shipment = $mapper->map(self::FULL, MelhorEnvioAddressMapper::PURPOSE_SHIPMENT);

    $this->assertSame($quote->toQuoteArray()['postal_code'], $shipment->toShipmentArray()['postal_code']);
  }

}
