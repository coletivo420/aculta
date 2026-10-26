<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\Contract\PaymentOperationsInterface;
use Drupal\aculta_portal\Exception\PaymentOperationException;
use Drupal\aculta_portal\Sales\SalesPaymentAccess;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Atualiza o status de um pagamento a partir do Mercado Pago (permissão por escopo do pedido).
 */
final class SalesPaymentRefreshForm extends FormBase {

  public function __construct(
    private readonly ?PaymentOperationsInterface $pagamentos,
    private readonly AccountInterface $usuario,
    private readonly EntityTypeManagerInterface $entidades,
  ) {}

  /**
   * Performs the create operation.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->has('aculta_portal.payment_provider.payment_operations')
        ? $container->get('aculta_portal.payment_provider.payment_operations')
        : NULL,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Performs the get form id operation.
   */
  public function getFormId(): string {
    return 'aculta_portal_sales_payment_refresh';
  }

  /**
   * Performs the build form operation.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PaymentInterface $commerce_payment = NULL): array {
    if ($this->pagamentos === NULL) {
      throw new AccessDeniedHttpException();
    }
    $this->autorizar($commerce_payment);
    $form_state->set('pagamento_id', (int) $commerce_payment->id());
    $form['explicacao'] = ['#markup' => '<p>' . $this->t('Consulta o pagamento no Mercado Pago e atualiza o status local. Não cobra nem altera o valor.') . '</p>'];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Atualizar status')];
    return $form;
  }

  /**
   * Performs the submit form operation.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pagamento = $this->carregar((int) $form_state->get('pagamento_id'));
    try {
      $status = $this->pagamentos?->refresh($pagamento) ?? '';
      $this->messenger()->addStatus($this->t('Status atualizado: @status.', ['@status' => $status]));
    }
    catch (\DomainException | PaymentOperationException $e) {
      $this->messenger()->addError($e->getMessage());
    }
    $form_state->setRedirect('aculta_portal.relatorio_vendas');
  }

  /**
   * Confere a permissão do escopo do pedido do pagamento. Recusa com 403 sem revelar o pedido.
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

  /**
   * Performs the carregar operation.
   */
  private function carregar(int $id): PaymentInterface {
    $pagamento = $this->entidades->getStorage('commerce_payment')->load($id);
    if (!$pagamento instanceof PaymentInterface) {
      throw new AccessDeniedHttpException();
    }
    return $pagamento;
  }

}
