<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Situação da identidade fiscal (CPF ou CNPJ) do titular no checkout.
 */
enum FiscalIdentityStatus: string {

  // Documento válido cadastrado em Outros Dados.
  case Valid = 'valid';

  // Nenhum documento cadastrado.
  case Missing = 'missing';

  // Não é possível confirmar o cadastro agora (serviço ausente ou valor
  // ilegível). Falha fechada.
  case Unavailable = 'unavailable';

}
