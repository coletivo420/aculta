<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\user\UserInterface;

/**
 * Política compartilhada de identidade fiscal do checkout comercial (Loja e LMS).
 *
 * Decisão de 2026-10-23: a Loja e o LMS exigem CPF/CNPJ válido para concluir pedidos comerciais. Doações não entram
 * aqui. O documento vem exclusivamente de `aculta_id_cpf` (contrato AccountDocumentInterface). Esta classe não copia
 * o documento para pedido, perfil, entidade ou sessão, e não recebe valor enviado pelo navegador.
 *
 * A exigência é decidida pelo tipo do pedido (contexto validado), nunca pela URL.
 */
final class FiscalIdentityPolicy {

  public function __construct(
    private readonly CheckoutSettings $settings,
    private readonly ?AccountDocumentInterface $document = NULL,
  ) {}

  /**
   * Tipos de pedido comerciais que exigem identidade fiscal: a Loja e os tipos reconhecidos como curso. */
  public function commercialOrderTypes(): array {
    return array_values(array_unique([$this->settings->shopOrderType(), ...$this->settings->lmsOrderTypes()]));
  }

  /**
   *
   */
  public function requiresDocument(OrderInterface $order): bool {
    return in_array($order->bundle(), $this->commercialOrderTypes(), TRUE);
  }

  /**
   * Consulta a situação do titular. Falha fechada: sem serviço ou com valor ilegível, não há confirmação.
   */
  public function check(UserInterface $account): FiscalIdentityCheck {
    if ($this->document === NULL || !$this->document->isAvailable()) {
      return new FiscalIdentityCheck(FiscalIdentityStatus::Unavailable);
    }
    try {
      $masked = $this->document->savedDocument($account);
    }
    catch (\RuntimeException) {
      return new FiscalIdentityCheck(FiscalIdentityStatus::Unavailable);
    }
    if ($masked === NULL) {
      return new FiscalIdentityCheck(FiscalIdentityStatus::Missing);
    }
    return new FiscalIdentityCheck(FiscalIdentityStatus::Valid, $masked);
  }

}
