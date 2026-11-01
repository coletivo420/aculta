<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Address;

use Drupal\aculta_portal\Account\StreetNumberField;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;

/**
 * Transforma o endereço estruturado do ACULTA em endereço logístico do Melhor Envio.
 *
 * Responsabilidades (ver docs/ADDRESS-CONTRACT.md):
 * - verificar país Brasil e normalizar o CEP para 8 dígitos (CepLookupInterface::normalize, sem HTTP);
 * - separar rua e número com StreetNumberField::split(), sem regex própria;
 * - preservar complemento, bairro, cidade e UF;
 * - validar o que a operação exige: cotação só CEP; contratação e etiqueta exigem número, bairro, cidade e UF.
 *
 * Não grava nada, não faz chamadas HTTP e não realiza operações financeiras. Nunca converte S/N em zero e nunca
 * inventa dado faltante: o que falta vira erro.
 */
final class MelhorEnvioAddressMapper {

  /** Cotação: só CEP de origem e destino. */
  public const PURPOSE_QUOTE = 'quote';

  /** Contratação no carrinho, geração e impressão de etiqueta: endereço completo. */
  public const PURPOSE_SHIPMENT = 'shipment';

  public const ERR_MISSING = 'address_missing';
  public const ERR_COUNTRY = 'country_not_brazil';
  public const ERR_POSTAL_CODE = 'postal_code_invalid';
  public const ERR_STREET = 'street_missing';
  public const ERR_NUMBER_MISSING = 'number_missing';
  public const ERR_NUMBER_INVALID = 'number_invalid';
  public const ERR_SN_NOT_ACCEPTED = 'sn_not_accepted_by_api';
  public const ERR_DISTRICT = 'district_missing';
  public const ERR_CITY = 'city_missing';
  public const ERR_STATE = 'state_invalid';
  public const ERR_PURPOSE = 'purpose_unknown';

  public function __construct(private readonly CepLookupInterface $cep) {}

  /**
   * @param array<string, mixed>|null $address
   *   Campos do Drupal Address de um perfil (perfil de envio do pedido, ou configuração da origem). Não é alterado.
   * @param string $purpose
   *   PURPOSE_QUOTE ou PURPOSE_SHIPMENT.
   */
  public function map(?array $address, string $purpose): LogisticsAddress {
    if (!in_array($purpose, [self::PURPOSE_QUOTE, self::PURPOSE_SHIPMENT], TRUE)) {
      return $this->invalid([self::ERR_PURPOSE]);
    }
    if ($address === NULL || $address === []) {
      return $this->invalid([self::ERR_MISSING]);
    }

    $errors = [];
    $country = strtoupper(trim((string) ($address['country_code'] ?? '')));
    if ($country !== 'BR') {
      $errors[] = self::ERR_COUNTRY;
    }

    $postalCode = $this->cep->normalize((string) ($address['postal_code'] ?? ''));
    if ($postalCode === NULL) {
      $errors[] = self::ERR_POSTAL_CODE;
    }

    $complement = trim((string) ($address['address_line2'] ?? ''));

    if ($purpose === self::PURPOSE_QUOTE) {
      // Cotação exige só o CEP. Os demais campos são preservados para não perder dados, mas não bloqueiam.
      return $errors === []
        ? new LogisticsAddress($postalCode, '', '', FALSE, $complement, '', '', '', 'BR')
        : $this->invalid($errors, $postalCode ?? '');
    }

    // Contratação: rua e número vêm do mesmo campo, separados pelo método do ACULTA.
    $parts = StreetNumberField::split((string) ($address['address_line1'] ?? ''));
    $street = $parts['street'];
    $number = $parts['number'];
    $sn = $parts['sn'];

    if ($street === '') {
      $errors[] = self::ERR_STREET;
    }
    if ($sn) {
      // A API não documenta representação para endereço sem número. Bloqueia, não converte para zero.
      $errors[] = self::ERR_SN_NOT_ACCEPTED;
    }
    elseif ($number === '') {
      $errors[] = self::ERR_NUMBER_MISSING;
    }
    elseif (!preg_match('/^[0-9]{1,' . StreetNumberField::MAX_DIGITS . '}$/', $number)) {
      $errors[] = self::ERR_NUMBER_INVALID;
    }

    $district = trim((string) ($address['dependent_locality'] ?? ''));
    if ($district === '') {
      $errors[] = self::ERR_DISTRICT;
    }
    $city = trim((string) ($address['locality'] ?? ''));
    if ($city === '') {
      $errors[] = self::ERR_CITY;
    }
    $state = strtoupper(trim((string) ($address['administrative_area'] ?? '')));
    if (!preg_match('/^[A-Z]{2}$/', $state)) {
      $errors[] = self::ERR_STATE;
    }

    if ($errors !== []) {
      return $this->invalid($errors, $postalCode ?? '');
    }

    return new LogisticsAddress(
      postalCode: $postalCode,
      street: $street,
      number: $number,
      sn: FALSE,
      complement: $complement,
      district: $district,
      city: $city,
      stateAbbr: $state,
      countryId: 'BR',
    );
  }

  /** @param list<string> $errors */
  private function invalid(array $errors, string $postalCode = ''): LogisticsAddress {
    return new LogisticsAddress($postalCode, '', '', FALSE, '', '', '', '', 'BR', array_values(array_unique($errors)));
  }

}
