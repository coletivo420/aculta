<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_portal\MercadoPago\MercadoPagoPaymentService;
use Drupal\aculta_portal\Sales\SalesPaymentAccess;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cancela um pagamento pendente no Mercado Pago (transição void). Confirmação obrigatória.
 */
final class SalesPaymentCancelForm extends ConfirmFormBase {

  public function __construct(
    private readonly MercadoPagoPaymentService $pagamentos,
    private readonly AccountInterface $usuario,
    private readonly EntityTypeManagerInterface $entidades,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.mercado_pago.payment_service'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'aculta_portal_sales_payment_cancel';
  }

  public function getQuestion(): string {
    return (string) $this->t('Cancelar este pagamento pendente no Mercado Pago?');
  }

  public function getDescription(): string {
    return (string) $this->t('Só funciona enquanto o pagamento está pendente, em processo ou autorizado. Pagamento aprovado precisa de estorno.');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_portal.relatorio_vendas');
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?PaymentInterface $commerce_payment = NULL): array {
    $this->autorizar($commerce_payment);
    $form_state->set('pagamento_id', (int) $commerce_payment->id());
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pagamento = $this->entidades->getStorage('commerce_payment')->load((int) $form_state->get('pagamento_id'));
    if (!$pagamento instanceof PaymentInterface) {
      throw new AccessDeniedHttpException();
    }
    try {
      $this->pagamentos->cancelar($pagamento);
      $this->messenger()->addStatus($this->t('Pagamento cancelado no Mercado Pago.'));
    }
    catch (\DomainException | MercadoPagoApiException $e) {
      $this->messenger()->addError($e->getMessage());
    }
    $form_state->setRedirect('aculta_portal.relatorio_vendas');
  }

  /**
   * Confere a permissão do escopo do pedido. Recusa com 403 sem revelar o pedido.
   */
  private function autorizar(?PaymentInterface $pagamento): void {
    $pedido = $pagamento?->getOrder();
    if (!$pedido instanceof OrderInterface) {
      throw new AccessDeniedHttpException();
    }
    $tipos = array_map(static fn ($item): string => (string) $item->bundle(), $pedido->getItems());
    if (!(new SalesPaymentAccess($this->usuario))->podeAgir(SalesPaymentAccess::escoposDoPedido($tipos))) {
      throw new AccessDeniedHttpException();
    }
  }

}
