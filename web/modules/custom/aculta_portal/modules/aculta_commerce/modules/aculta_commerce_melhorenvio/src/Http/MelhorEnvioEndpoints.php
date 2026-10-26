<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

/**
 * Caminhos da API do Melhor Envio usados pelo cliente.
 *
 * Estado de confirmação (pendência ME-PEND-01):
 * - QUOTE (`/api/v2/me/shipment/calculate`): citado como rota atual da documentação em comentário
 *   do fórum oficial (docs.melhorenvio.com.br/discuss/60e5f0d3903031006c9ffef3). Não é resposta
 *   de suporte, e a referência de cotação não foi lida. Validar no sandbox antes do uso real.
 * - AUTHORIZE e TOKEN: não confirmados na documentação pública consultada. Validar no sandbox.
 * Mantidos isolados aqui para que a correção seja uma troca de constante. Nenhum deles deve ser
 * chamado em produção antes da validação.
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
