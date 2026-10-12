<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Slugs públicos do checkout (em português) e o mapeamento para as rotas internas do Commerce.
 *
 * As rotas do Commerce continuam em /checkout/{pedido}/{etapa}. O caminho público é /finalizar-compra/{pedido}/{etapa-pt}.
 * O processador de caminho (CheckoutFriendlyPathProcessor) gera e reconhece o caminho público; a redireção de
 * /checkout (DomainPurposeRequestSubscriber) leva as URLs antigas (GET) ao caminho público com 301. Os retornos
 * do Mercado Pago (return e cancel) seguem a mesma regra, porque são rotas /checkout/{pedido}/{etapa}/return|cancel.
 * Ver docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md.
 */
final class CheckoutSlugMap {

  public const PUBLIC_PREFIX = '/finalizar-compra';

  /** Etapa pública => etapa interna do fluxo Commerce. */
  public const STEPS = [
    'entrar' => 'login',
    'informacoes-do-pedido' => 'order_information',
    'revisao' => 'review',
    'pagamento' => 'payment',
    'concluido' => 'complete',
  ];

  /** Caminho interno /checkout/... para o público /finalizar-compra/... ; NULL se não for do checkout. */
  public static function toPublic(string $path): ?string {
    if (!preg_match('#^/checkout/(\d+)(?:/([a-z_]+))?(/.*)?$#', $path, $m)) {
      return NULL;
    }
    $step = $m[2] ?? '';
    $public_step = $step === '' ? '' : (array_search($step, self::STEPS, TRUE) ?: $step);
    return self::PUBLIC_PREFIX . '/' . $m[1] . ($public_step !== '' ? '/' . $public_step : '') . ($m[3] ?? '');
  }

  /** Caminho público /finalizar-compra/... para o interno /checkout/... ; NULL se não for do checkout público. */
  public static function toInternal(string $path): ?string {
    if (!preg_match('#^' . preg_quote(self::PUBLIC_PREFIX, '#') . '/(\d+)(?:/([a-z-]+))?(/.*)?$#', $path, $m)) {
      return NULL;
    }
    $step = $m[2] ?? '';
    $internal_step = $step === '' ? '' : (self::STEPS[$step] ?? $step);
    return '/checkout/' . $m[1] . ($internal_step !== '' ? '/' . $internal_step : '') . ($m[3] ?? '');
  }

}
