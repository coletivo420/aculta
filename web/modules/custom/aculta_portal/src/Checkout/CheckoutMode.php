<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Modalidades de checkout que um contexto comercial (Loja, LMS, Doações) oferece.
 *
 * Vocabulário neutro: cada provider traduz sua configuração própria para estes valores
 * (por exemplo, `donation_route` do Donation: donate → Dedicated, cart → Common, both → Both).
 * Descreve disponibilidade; não escolhe o fluxo nem guarda preferência de usuário ou sessão.
 */
enum CheckoutMode: string {

  /** Somente o checkout dedicado do contexto. */
  case Dedicated = 'dedicated';

  /** Somente o carrinho e o checkout comuns do Commerce. */
  case Common = 'common';

  /** Os dois. */
  case Both = 'both';

  public function allowsDedicated(): bool {
    return $this !== self::Common;
  }

  public function allowsCommon(): bool {
    return $this !== self::Dedicated;
  }

}
