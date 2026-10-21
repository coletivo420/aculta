<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Domain\PurposeResolverInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Grava o purpose de origem do pedido no momento da criação.
 *
 * O purpose (por exemplo `main`) é o identificador estável do domínio no ACULTA. Fica
 * nos dados do pedido do Commerce (`getData`), sem alterar o esquema da entidade.
 * Pedidos anteriores à regra, ou criados fora de um purpose conhecido, ficam sem valor
 * e são contados como `sem_dominio`.
 */
final class CommerceOrderDomainHooks {

  /**
   * Chave dos dados do pedido que guarda o purpose de origem.
   */
  public const DATA_KEY = 'aculta_statistics_purpose';

  public function __construct(
    private readonly PurposeResolverInterface $purposes,
  ) {}

  /**
   * Preenche o purpose de origem em pedido novo, uma única vez.
   */
  #[Hook('commerce_order_presave')]
  public function presave(OrderInterface $order): void {
    if (!$order->isNew() || $order->getData(self::DATA_KEY)) {
      return;
    }
    $purpose = $this->purposes->currentPurpose();
    if ($purpose !== NULL && preg_match('/^[a-z0-9_-]{1,32}$/', $purpose) === 1) {
      $order->setData(self::DATA_KEY, $purpose);
    }
  }

}
