<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\aculta_commerce_melhorenvio\Address\ShippingAddressAdapter;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use Drupal\Tests\UnitTestCase;

#[CoversClass(ShippingAddressAdapter::class)]
#[Group('aculta_commerce_melhorenvio')]
class ShippingAddressAdapterTest extends UnitTestCase {

  /** Endereço completo que não deve gerar consulta. */
  private const COMPLETE = [
    'country_code' => 'BR',
    'postal_code' => '01310-100',
    'address_line1' => 'Avenida Paulista',
    'address_line2' => '1000',
    'dependent_locality' => 'Bela Vista',
    'locality' => 'São Paulo',
    'administrative_area' => 'SP',
  ];

  /**
   * CEP informado, sem os campos de logradouro (usuário digitou só o CEP e o número).
   */
  private const CEP_ONLY = [
    'country_code' => 'BR',
    'postal_code' => '01310100',
    'address_line1' => '',
    'address_line2' => '1000',
    'dependent_locality' => '',
    'locality' => 'Cidade digitada',
    'administrative_area' => '',
  ];

  /**
   */
  public function testInvalidPostalCodeDoesNotLookUp(): void {
    $lookup = new FakeCepLookup();
    $result = (new ShippingAddressAdapter($lookup))->resolve(['postal_code' => '0131']);

    $this->assertSame(ShippingAddressAdapter::STATUS_INVALID, $result['status']);
    $this->assertNull($result['postal_code']);
    $this->assertSame(0, $lookup->calls);
  }

  /**
   */
  public function testMissingPostalCodeIsInvalid(): void {
    $result = (new ShippingAddressAdapter(new FakeCepLookup()))->resolve([]);

    $this->assertSame(ShippingAddressAdapter::STATUS_INVALID, $result['status']);
  }

  /**
   * CEP com máscara vira 8 dígitos antes de qualquer consulta ou envio.
   */
  public function testMaskedPostalCodeIsNormalized(): void {
    $lookup = new FakeCepLookup();
    $result = (new ShippingAddressAdapter($lookup))->resolve(self::COMPLETE);

    $this->assertSame(ShippingAddressAdapter::STATUS_COMPLETE, $result['status']);
    $this->assertSame('01310100', $result['postal_code']);
  }

  /**
   * Endereço completo não consulta a base de CEP.
   */
  public function testCompleteAddressDoesNotLookUp(): void {
    $lookup = new FakeCepLookup();
    $result = (new ShippingAddressAdapter($lookup))->resolve(self::COMPLETE);

    $this->assertSame([], $result['missing']);
    $this->assertSame([], $result['suggestion']);
    $this->assertSame(0, $lookup->calls);
  }

  /**
   * Com CEP válido e campos faltando, a sugestão preenche só os vazios e não toca em número nem em cidade digitada.
   */
  public function testSuggestionFillsOnlyMissingFields(): void {
    $lookup = new FakeCepLookup(found: [
      'postal_code' => '01310100',
      'address_line1' => 'Avenida Paulista',
      'dependent_locality' => 'Bela Vista',
      'locality' => 'São Paulo',
      'administrative_area' => 'SP',
    ]);
    $result = (new ShippingAddressAdapter($lookup))->resolve(self::CEP_ONLY);

    $this->assertSame(ShippingAddressAdapter::STATUS_SUGGESTED, $result['status']);
    $this->assertSame(1, $lookup->calls);
    $this->assertSame(['address_line1', 'dependent_locality', 'administrative_area'], $result['missing']);
    $this->assertSame(
      ['address_line1' => 'Avenida Paulista', 'dependent_locality' => 'Bela Vista', 'administrative_area' => 'SP'],
      $result['suggestion'],
    );
    $this->assertArrayNotHasKey('locality', $result['suggestion']);
    $this->assertArrayNotHasKey('address_line2', $result['suggestion']);
  }

  /**
   * CEP não encontrado: nenhum endereço é inventado.
   */
  public function testNotFoundReturnsNoSuggestion(): void {
    $result = (new ShippingAddressAdapter(new FakeCepLookup(found: NULL)))->resolve(self::CEP_ONLY);

    $this->assertSame(ShippingAddressAdapter::STATUS_NOT_FOUND, $result['status']);
    $this->assertSame([], $result['suggestion']);
  }

  /**
   * Falha da consulta não derruba o checkout.
   */
  public function testLookupFailureIsUnavailableNotFatal(): void {
    $result = (new ShippingAddressAdapter(new FakeCepLookup(throw: TRUE)))->resolve(self::CEP_ONLY);

    $this->assertSame(ShippingAddressAdapter::STATUS_UNAVAILABLE, $result['status']);
    $this->assertSame('01310100', $result['postal_code']);
    $this->assertSame([], $result['suggestion']);
  }

}

/**
 * Implementação falsa do contrato de consulta, com contador de chamadas.
 */
final class FakeCepLookup implements CepLookupInterface {

  public int $calls = 0;

  /** @param array<string, string>|null $found */
  public function __construct(
    private readonly ?array $found = NULL,
    private readonly bool $throw = FALSE,
  ) {}

  public function normalize(string $cep): ?string {
    $digits = preg_replace('/\D+/', '', $cep) ?? '';
    return strlen($digits) === 8 ? $digits : NULL;
  }

  public function lookup(string $cep): ?array {
    $this->calls++;
    if ($this->throw) {
      throw new \RuntimeException('fonte indisponível');
    }
    return $this->found;
  }

}
