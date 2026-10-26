<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Webhook;

/**
 * Valida o cabeçalho `X-ME-Signature` do webhook do Melhor Envio.
 *
 * A assinatura é HMAC-SHA256 do corpo bruto, com o segredo do aplicativo como chave. A documentação não informa a
 * codificação do hash; por isso aceita base64 e hexadecimal. Comparação em tempo constante.
 */
final class MelhorEnvioWebhookSignature {

  /**
   * Verdadeiro somente se o cabeçalho bater com o HMAC do corpo, em uma das duas codificações aceitas.
   */
  public function isValid(string $body, string $signature, string $secret): bool {
    $signature = trim($signature);
    if ($secret === '' || $signature === '') {
      return FALSE;
    }
    $raw = hash_hmac('sha256', $body, $secret, TRUE);
    foreach ([base64_encode($raw), bin2hex($raw)] as $expected) {
      if (hash_equals($expected, $signature)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
