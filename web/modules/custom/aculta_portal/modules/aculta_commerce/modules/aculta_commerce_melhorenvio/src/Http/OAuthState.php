<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

/**
 * Parâmetro `state` do OAuth2: gera um valor aleatório e confere o retorno sem vazamento de tempo.
 *
 * Protege o retorno da autorização contra CSRF: só aceita o código se o `state` devolvido for o emitido para a
 * mesma sessão.
 */
final class OAuthState {

  /**
   * Gera um valor aleatório, de 32 bytes em hexadecimal.
   */
  public function issue(): string {
    return bin2hex(random_bytes(32));
  }

  /**
   * Verdadeiro somente se o valor recebido for igual ao emitido, em tempo constante.
   */
  public function matches(?string $expected, ?string $received): bool {
    if ($expected === NULL || $expected === '' || $received === NULL || $received === '') {
      return FALSE;
    }
    return hash_equals($expected, $received);
  }

}
