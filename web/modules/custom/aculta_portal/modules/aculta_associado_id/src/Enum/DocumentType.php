<?php

declare(strict_types=1);

namespace Drupal\aculta_associado_id\Enum;

/**
 * The supported document types; documents are always strings.
 */
enum DocumentType: string {

  case CPF = 'cpf';
  case CNPJ = 'cnpj';

}
