<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;

/**
 * URL absoluta de um caminho do site público, no host do ambiente (ver ENDERECOS-AMBIENTE.md).
 *
 * Implementada pelo aculta_portal (DomainPurposeManager), para que o e-mail nunca leve o host de linha de comando
 * (ex.: http://default) nem um host fixo no código.
 */
interface PublicUrlInterface {

  /**
   * URL absoluta do caminho (que começa com "/"), ou vazio se não houver host para o caminho.
   */
  public function absolute(string $path): string;

}
