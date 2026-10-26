<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

/**
 * Caminhos da API do Melhor Envio usados pelo cliente.
 *
 * Estado de confirmação (ME-PEND-01, fechada em 2026-10-26 no sandbox):
 * - AUTHORIZE: tela de consentimento, validada com o aplicativo de teste. Não aparece na especificação
 *   da API (é página, não endpoint).
 * - TOKEN: troca de código e renovação, confirmados na especificação e no sandbox. `expires_in` de
 *   2592000 s (30 dias).
 * - QUOTE: confirmado na especificação e no sandbox (200 com cotações), escopo `shipping-calculate`.
 */
final class MelhorEnvioEndpoints {

  /**
   * Tela de consentimento OAuth2.
   */
  public const AUTHORIZE = '/oauth/authorize';

  /**
   * Troca de código e renovação de token, via POST.
   */
  public const TOKEN = '/oauth/token';

  /**
   * Cotação de frete, via POST.
   */
  public const QUOTE = '/api/v2/me/shipment/calculate';

}
