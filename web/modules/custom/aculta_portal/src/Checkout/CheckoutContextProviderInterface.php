<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Contrato de um contexto comercial que participa da orquestração de checkout do Portal.
 *
 * Implementado por cada módulo proprietário (Loja, LMS, Doações), que continua dono das suas regras e da sua
 * configuração; o Portal apenas consulta. Registrar com a tag `aculta_portal.checkout_provider`.
 * O Portal nunca depende de um provider concreto, o que evita dependência circular entre módulos.
 */
interface CheckoutContextProviderInterface {

  /** ID estável e único do contexto (por exemplo, `shop`, `lms`, `donation`). */
  public function id(): string;

  /** Rótulo para a administração. */
  public function label(): string|\Stringable;

  /** Ordem de apresentação e de desempate; menor primeiro. */
  public function weight(): int;

  /** Configuração efetiva atual, lida da configuração do módulo proprietário. */
  public function configuration(): CheckoutContextConfiguration;

}
