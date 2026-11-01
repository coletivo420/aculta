<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Enum;

/**
 * The supported document types; documents are always strings.
 */
enum DocumentType: string {

  // Keep statutory abbreviations intact in the public enum API.
  // phpcs:ignore Drupal.NamingConventions.ValidEnumCase.NoUpperAcronyms
  case CPF = 'cpf';
  // phpcs:ignore Drupal.NamingConventions.ValidEnumCase.NoUpperAcronyms
  case CNPJ = 'cnpj';

}
