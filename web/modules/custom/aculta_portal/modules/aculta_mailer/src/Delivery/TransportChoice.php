<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Escolha do transporte disponível: um transporte esgotado (cota) ou desligado é ignorado, inclusive a contingência.
 *
 * Sem transporte disponível, mantém o pedido original: a falha segue com o erro real do provedor, sem trocar sem motivo.
 */
final class TransportChoice {

  /**
   * Transporte a usar.
   *
   * @param string|null $requested
   *   Transporte pedido, ou NULL para o padrão.
   * @param string $primary
   *   Transporte padrão (usado quando NULL).
   * @param string|null $fallback
   *   Transporte de contingência.
   * @param callable(string): bool $available
   *   Indica se o transporte pode receber envios agora.
   */
  public static function choose(?string $requested, string $primary, ?string $fallback, callable $available): ?string {
    $effective = $requested ?? $primary;
    if ($available($effective)) {
      return $requested;
    }
    if ($fallback !== NULL && $fallback !== $effective && $available($fallback)) {
      return $fallback;
    }
    return $requested;
  }

}
