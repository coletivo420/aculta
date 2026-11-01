<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Lookup;

/**
 * Contract for the Portal's shared CEP lookup service.
 *
 * PHP modules depend on this interface instead of the implementation.
 */
interface CepLookupInterface {

  /**
   * Normalizes a CEP value.
   *
   * @param string $cep
   *   CEP with or without its standard mask.
   *
   * @return string|null
   *   Eight digits, or NULL when the input format is invalid.
   */
  public function normalize(string $cep): ?string;

  /**
   * Looks up an address using the configured sources.
   *
   * Returns Drupal Address keys: postal_code, address_line1,
   * dependent_locality, locality, and administrative_area.
   *
   * @param string $cep
   *   CEP to look up, with or without its standard mask.
   *
   * @return array<string, string>|null
   *   Address data, or NULL when the CEP is invalid or not found.
   */
  public function lookup(string $cep): ?array;

}
