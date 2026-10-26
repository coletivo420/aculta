<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

/**
 * Caminhos da API do Melhor Envio usados pelo cliente.
 *
 * ATENÇÃO: os três caminhos abaixo NÃO estão confirmados na documentação pública consultada
 * (docs.melhorenvio.com.br, páginas de introdução e autenticação). Mantidos isolados aqui para
 * que a correção seja uma troca de constante. Validar no sandbox antes de qualquer uso real
 * (pendência ME-PEND-01). Nenhum deles deve ser chamado em produção antes dessa validação.
 *
 * O campo `expires_in` da resposta de token segue o padrão OAuth2 (RFC 6749) e também precisa ser
 * confirmado no sandbox.
 */
final class MelhorEnvioEndpoints {

  /**
   * Tela de consentimento OAuth2. Não confirmado na documentação pública (ME-PEND-01).
   */
  public const AUTHORIZE = '/oauth/authorize';

  /**
   * Troca de código e renovação de token, via POST. Não confirmado (ME-PEND-01).
   */
  public const TOKEN = '/oauth/token';

  /**
   * Cotação de frete. Não confirmado (ME-PEND-01). Ainda não usado pelo cliente.
   */
  public const QUOTE = '/api/v2/me/shipment/calculate';

}
