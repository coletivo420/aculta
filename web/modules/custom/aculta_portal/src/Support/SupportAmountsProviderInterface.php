<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Support;

/**
 * Fonte dos valores sugeridos da página de apoio (campanha vigente, quando houver).
 *
 * Contrato neutro: o aculta_portal não conhece o módulo que implementa a campanha. Sem implementação
 * habilitada, a página usa os valores padrão. O módulo aculta_donation fornece a implementação.
 */
interface SupportAmountsProviderInterface {

  /**
   * Valores e permissão de valor personalizado da campanha vigente, ou NULL sem campanha.
   *
   * @return array{amounts: int[], customAmount: bool}|null
   */
  public function valoresSugeridos(): ?array;

}
