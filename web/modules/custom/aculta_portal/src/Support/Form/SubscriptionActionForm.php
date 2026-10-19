<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Support\Form;

use Drupal\aculta_portal\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_portal\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Confirmação de pausar, reativar ou cancelar a doação mensal, na área "Meu apoio".
 *
 * Só o dono do pedido aplica a ação, e só quando o status atual permite (ver MercadoPagoSubscriptionRecorder::ACOES).
 * A ação é gravada no Mercado Pago antes de o pedido mudar; falha do Mercado Pago não altera o pedido.
 */
final class SubscriptionActionForm extends ConfirmFormBase {

  /** Ação validada no buildForm (pausar, reativar ou cancelar). */
  private string $acaoAtual = '';

  public function __construct(
    private readonly MercadoPagoSubscriptionRecorder $assinaturas,
    private readonly EntityTypeManagerInterface $entidades,
    private readonly AccountInterface $usuario,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.mercado_pago.subscription_recorder'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'aculta_portal_subscription_action';
  }

  public function getQuestion(): string {
    return match ($this->acaoAtual) {
      'pausar' => (string) $this->t('Pausar a doação mensal?'),
      'reativar' => (string) $this->t('Reativar a doação mensal?'),
      default => (string) $this->t('Cancelar a doação mensal?'),
    };
  }

  public function getDescription(): string {
    return (string) $this->t('Doações já feitas não são estornadas. Você pode reativar uma assinatura pausada enquanto ela existir.');
  }

  public function getConfirmText(): string {
    return (string) match ($this->acaoAtual) {
      'pausar' => $this->t('Pausar'),
      'reativar' => $this->t('Reativar'),
      default => $this->t('Cancelar assinatura'),
    };
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_portal.support_my');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Pedido de outra conta, ou ação não permitida para o status atual.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?OrderInterface $commerce_order = NULL, string $acao = ''): array {
    $acao = $this->acao($acao);
    if ($commerce_order === NULL || $acao === '' || (int) $commerce_order->getCustomerId() !== (int) $this->usuario->id()
      || !$this->assinaturas->podeAlterar($commerce_order, $acao)) {
      throw new AccessDeniedHttpException();
    }
    $this->acaoAtual = $acao;
    $form_state->set('pedido_id', (int) $commerce_order->id());
    $form_state->set('acao', $acao);
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pedido = $this->entidades->getStorage('commerce_order')->load((int) $form_state->get('pedido_id'));
    $gateway = $this->entidades->getStorage('commerce_payment_gateway')->load('mercado_pago');
    if (!$pedido instanceof OrderInterface || $gateway === NULL) {
      $this->messenger()->addError($this->t('Não foi possível atualizar a assinatura. Tente novamente em instantes.'));
    }
    else {
      try {
        $this->assinaturas->alterar($gateway, $pedido, (string) $form_state->get('acao'));
        $this->messenger()->addStatus($this->t('A assinatura foi atualizada.'));
      }
      catch (MercadoPagoApiException | \InvalidArgumentException) {
        // Detalhes técnicos ficam no log do gravador; o doador vê só a mensagem geral.
        $this->messenger()->addError($this->t('Não foi possível atualizar a assinatura agora. Tente novamente em instantes.'));
      }
    }
    $form_state->setRedirect('aculta_portal.support_my');
  }

  /**
   * Ação válida ou string vazia.
   */
  private function acao(mixed $valor): string {
    return is_string($valor) && isset(MercadoPagoSubscriptionRecorder::ACOES[$valor]) ? $valor : '';
  }

}
