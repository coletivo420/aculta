<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\user\UserInterface;

/**
 * Identidade fiscal compartilhada do checkout comercial (Loja e LMS).
 *
 * Decisão de 2026-10-23: a Loja e o LMS exigem CPF/CNPJ válido para concluir
 * pedidos comerciais. Doações não entram aqui. O documento vem exclusivamente
 * de `aculta_id_cpf` (contrato AccountDocumentInterface). Esta classe não copia
 * o documento para pedido, perfil ou sessão, e não recebe valor enviado pelo
 * navegador.
 *
 * A exigência é decidida pelo tipo do pedido (contexto validado), nunca pela
 * URL.
 */
final class FiscalIdentityPolicy {

  /**
   * Cria a política.
   *
   * @param \Drupal\aculta_portal\Checkout\CheckoutSettings $settings
   *   Tipos de pedido configurados para Loja e LMS.
   * @param \Drupal\aculta_portal\Account\AccountDocumentInterface|null $document
   *   Serviço de documento, ou NULL quando `aculta_id_cpf` não está habilitado.
   */
  public function __construct(
    private readonly CheckoutSettings $settings,
    private readonly ?AccountDocumentInterface $document = NULL,
  ) {}

  /**
   * Tipos de pedido comercial avaliados pela política fiscal: Loja e LMS.
   *
   * @return list<string>
   *   IDs dos tipos de pedido.
   */
  public function commercialOrderTypes(): array {
    return array_values(array_unique([$this->settings->shopOrderType(), ...$this->settings->lmsOrderTypes()]));
  }

  /**
   * Indica se o pedido exige identidade fiscal.
   *
   * Só o LMS exige: o CPF vai ao certificado. A loja não exige (decisão do responsável, 2026-10-28; o Mercado Pago
   * não exige CPF nem endereço no pagador). No LMS, apenas cursos com preço base maior que zero exigem;
   * pedidos sem composição reconhecível continuam fail-closed.
   */
  public function requiresDocument(OrderInterface $order): bool {
    if ($order->bundle() === $this->settings->shopOrderType()) {
      return FALSE;
    }
    if (!in_array($order->bundle(), $this->settings->lmsOrderTypes(), TRUE)) {
      return FALSE;
    }

    // LMS orders are used for both paid and free enrollments. Decide from the
    // base price of each purchased course variation, not the adjusted order
    // total: a promotion must not turn a paid course into a free enrollment.
    $items = $order->getItems();
    if ($items === []) {
      return TRUE;
    }
    foreach ($items as $item) {
      if (!$item instanceof OrderItemInterface) {
        return TRUE;
      }
      $variation = $item->getPurchasedEntity();
      if (!$variation instanceof ProductVariationInterface || $variation->bundle() !== 'curso') {
        return TRUE;
      }
      $price = $variation->getPrice();
      if (!$price instanceof Price || !$price->isZero()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Consulta a situação do titular.
   *
   * Falha fechada: sem serviço ou com valor ilegível, não há confirmação.
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

  /**
   * Indica se o checkout pode gravar o primeiro documento.
   *
   * Exige serviço disponível, com cifra, HMAC e campos prontos.
   */
  public function canFill(): bool {
    return $this->document !== NULL && $this->document->canSave();
  }

  /**
   * Valida a entrada e devolve o valor canônico, ou NULL.
   *
   * Nunca inclui a entrada em mensagens.
   */
  public function canonicalize(string $input): ?string {
    return $this->document?->canonicalize($input);
  }

  /**
   * Grava o primeiro documento do titular, uma única vez.
   *
   * Devolve FALSE quando já existe, é duplicado ou não pode ser gravado. A
   * mensagem ao usuário é sempre a mesma, para não revelar se o documento
   * existe em outra conta.
   */
  public function fill(UserInterface $account, string $canonical): bool {
    if (!$this->canFill()) {
      return FALSE;
    }
    try {
      $this->document->save($account, $canonical);
      return TRUE;
    }
    catch (\LogicException | \RuntimeException) {
      return FALSE;
    }
  }

}
