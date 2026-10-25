<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\Ga4;

/**
 * Configuração do GA4 lida do arquivo de segredos (nunca do código nem de config/sync).
 */
interface Ga4Settings {

  /**
   * ID numérico da propriedade GA4 (variável GA4_PROPERTY_ID), ou NULL quando não configurada.
   */
  public function propertyId(): ?string;

  /**
   * Chave da conta de serviço decodificada (variável GA4_SERVICE_ACCOUNT_JSON, em base64), ou NULL.
   *
   * Só devolve chave com tipo `service_account` e chave privada. Nunca registrar o retorno.
   *
   * @return array<string, mixed>|null
   */
  public function serviceAccount(): ?array;

}
