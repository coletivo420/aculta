<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Leitura de `aculta_portal.checkout_settings`, com padrões que reproduzem o comportamento anterior à config.
 *
 * Sem a config (site que ainda não a importou) valem: checkout comum habilitado, tipo de pedido da Loja `default`
 * e do LMS `curso`. Nenhuma preferência por usuário ou sessão é guardada aqui.
 */
final class CheckoutSettings {

  public const CONFIG = 'aculta_portal.checkout_settings';

  public const DEFAULT_SHOP_ORDER_TYPE = 'default';

  /** Tipo de pedido de curso histórico: continua reconhecido mesmo se o LMS passar a usar outro. */
  public const LEGACY_LMS_ORDER_TYPE = 'curso';

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /** Chave global: FALSE fecha as rotas globais de carrinho e checkout do Commerce. */
  public function commonCheckoutEnabled(): bool {
    $valor = $this->configFactory->get(self::CONFIG)->get('common_checkout_enabled');
    return $valor === NULL ? TRUE : (bool) $valor;
  }

  public function shopOrderType(): string {
    return $this->texto('shop_order_type', self::DEFAULT_SHOP_ORDER_TYPE);
  }

  /** Tipo de pedido com que o LMS cria novas compras de curso. */
  public function lmsOrderType(): string {
    return $this->texto('lms_order_type', self::LEGACY_LMS_ORDER_TYPE);
  }

  /**
   * Tipos de pedido reconhecidos como curso: o configurado primeiro e o histórico `curso`, para que pedidos já
   * feitos continuem concedendo matrícula, aparecendo no separador de cursos e sem estorno.
   *
   * @return list<string>
   */
  public function lmsOrderTypes(): array {
    return array_values(array_unique([$this->lmsOrderType(), self::LEGACY_LMS_ORDER_TYPE]));
  }

  private function texto(string $chave, string $padrao): string {
    $valor = $this->configFactory->get(self::CONFIG)->get($chave);
    return is_string($valor) && $valor !== '' ? $valor : $padrao;
  }

}
