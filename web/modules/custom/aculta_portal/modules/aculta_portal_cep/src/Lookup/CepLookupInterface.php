<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Lookup;

/**
 * Contrato da consulta de CEP do Portal. Módulos PHP dependem desta interface, não da implementação.
 */
interface CepLookupInterface {

  /** CEP com 8 dígitos (com ou sem máscara) ou NULL quando o formato é inválido. */
  public function normalize(string $cep): ?string;

  /**
   * Endereço do CEP em Drupal Address (postal_code, address_line1, dependent_locality, locality,
   * administrative_area). NULL quando nenhuma fonte acha o CEP ou o formato é inválido.
   *
   * @return array<string, string>|null
   */
  public function lookup(string $cep): ?array;

}
