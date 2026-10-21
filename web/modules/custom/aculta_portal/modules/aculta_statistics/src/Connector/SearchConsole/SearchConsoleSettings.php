<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\SearchConsole;

/**
 * Configuração do Search Console lida do arquivo de segredos. A conta de serviço é a mesma do GA4.
 */
interface SearchConsoleSettings {

  /**
   * Propriedade do Search Console (`sc-domain:dominio` ou URL com prefixo), ou NULL quando não configurada.
   */
  public function siteUrl(): ?string;

  /**
   * Chave da conta de serviço (mesma do GA4), ou NULL. Nunca registrar o retorno.
   *
   * @return array<string, mixed>|null
   */
  public function serviceAccount(): ?array;

}
