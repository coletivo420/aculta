<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutPane;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_portal\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_portal\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Autorização da doação mensal no Mercado Pago (assinatura de doação).
 *
 * Só atua em pedido do tipo `assinatura` sem assinatura ainda criada. Cria a assinatura (API de assinaturas do
 * Mercado Pago, sem plano: valor e frequência vão na própria assinatura), envia o pedido para aguardando autorização
 * e leva o doador ao init_point. Por lançar redirecionamento, esta pane impede que o pagamento avulso
 * (payment_process) rode no mesmo passo. O retorno cai na etapa de conclusão; a cobrança aprovada chega pelo webhook.
 *
 * Falha ao criar a assinatura não quebra a página: o doador vê uma mensagem geral no mesmo passo, e o detalhe técnico
 * vai para o log. Não cobra no checkout e não guarda cartão: o Mercado Pago cobra na autorização.
 */
#[CommerceCheckoutPane(
  id: 'donation_recurring_authorization',
  label: new TranslatableMarkup('Autorização da doação mensal'),
  default_step: 'payment',
  wrapper_element: 'container',
)]
final class RecurringAuthorizationPane extends CheckoutPaneBase {

  /** Gateway do Mercado Pago (o mesmo do pagamento avulso). */
  private const GATEWAY = 'mercado_pago';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CheckoutFlowInterface $checkout_flow,
    EntityTypeManagerInterface $entity_type_manager,
    private readonly MercadoPagoRecurrenceService $recorrencia,
    private readonly DomainPurposeManager $purposes,
    private readonly LoggerChannelInterface $logger,
    private readonly MessengerInterface $avisos,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('aculta_portal.mercado_pago.recurrence_service'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('aculta_portal.mercado_pago.logger'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return $this->order->bundle() === MercadoPagoSubscriptionRecorder::TIPO_PEDIDO
      && !$this->order->getData(MercadoPagoSubscriptionRecorder::CHAVE_ASSINATURA);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['mensagem'] = [
      '#markup' => $this->t('Você será levado ao Mercado Pago para autorizar a doação mensal. A primeira cobrança acontece na autorização.'),
    ];
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   *   Leva ao Mercado Pago para autorizar a assinatura, ou volta ao passo com a mensagem de erro.
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   Gateway do Mercado Pago ausente (configuração).
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')->load(self::GATEWAY);
    if ($gateway === NULL) {
      throw new PaymentGatewayException('Gateway do Mercado Pago ausente.');
    }
    $total = $this->order->getTotalPrice();
    $retorno = $this->purposes->routeUrl('main', 'aculta_donation.donation_controller_formPage', [
      'commerce_order' => $this->order->id(),
      'step' => 'complete',
    ]);
    try {
      $assinatura = $this->recorrencia->criarAssinatura($gateway, [
        'email' => (string) $this->order->getEmail(),
        'back_url' => $retorno?->toString() ?? '',
        'referencia' => (string) $this->order->id(),
        'motivo' => (string) new TranslatableMarkup('Doação mensal à ACULTA'),
        'valor' => number_format((float) $total->getNumber(), 2, '.', ''),
        'moeda' => $total->getCurrencyCode(),
        'frequencia' => 1,
      ]);
      if (empty($assinatura['init_point'])) {
        throw new MercadoPagoApiException('O Mercado Pago não devolveu o link de autorização.');
      }
    }
    catch (MercadoPagoApiException | \InvalidArgumentException $e) {
      $this->logger->error('Autorização da doação mensal do pedido @pedido falhou: @motivo', [
        '@pedido' => $this->order->id(),
        '@motivo' => $e->getMessage(),
      ]);
      $this->avisos->addError($this->t('Não foi possível iniciar a autorização da doação mensal no Mercado Pago. Confira o e-mail do pedido e tente novamente em instantes.'));
      $this->checkoutFlow->redirectToStep($this->checkoutFlow->getStepId());
    }

    $this->order->setData(MercadoPagoSubscriptionRecorder::CHAVE_ASSINATURA, $assinatura['id']);
    $this->order->setData(MercadoPagoSubscriptionRecorder::CHAVE_STATUS, (string) ($assinatura['status'] ?? MercadoPagoSubscriptionRecorder::STATUS_PENDENTE));
    $estado = $this->order->getState();
    if (isset($estado->getTransitions()['place'])) {
      $estado->applyTransitionById('place');
    }
    $this->order->save();
    throw new NeedsRedirectException($assinatura['init_point']);
  }

}
