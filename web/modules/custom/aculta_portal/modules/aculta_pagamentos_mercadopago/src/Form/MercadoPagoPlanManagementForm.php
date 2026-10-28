<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Form;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administrative Studio interface for subscription plans in test mode. */
final class MercadoPagoPlanManagementForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MercadoPagoRecurrenceService $recurrence,
    private readonly DomainPurposeManager $domainPurposes,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_pagamentos_mercadopago.recurrence_service'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_pagamentos_mercadopago_plan_management';
  }

  /**
   * {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // All external-provider form submissions use the canonical integration
    // endpoint, even when Studio renders this form in the ACCOUNT domain.
    $integrationUrl = $this->domainPurposes->routeUrl('main', 'aculta_pagamentos_mercadopago.plans');
    $form['#action'] = $integrationUrl?->toString() ?? Url::fromRoute('aculta_pagamentos_mercadopago.plans')->toString();
    $gateways = $this->testGateways();
    $form['intro'] = ['#markup' => $this->t('Planos Mercado Pago são modelos de assinatura. Esta interface acessa somente gateways em modo sandbox.')];
    if ($gateways === []) {
      $form['unavailable'] = ['#markup' => $this->t('Não há gateway Mercado Pago ativo em modo sandbox.')];
      return $form;
    }
    $gateway = reset($gateways);
    $form['reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do plano'),
      '#maxlength' => 120,
      '#required' => TRUE,
    ];
    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Valor por cobrança (BRL)'),
      '#min' => '0.01',
      '#max' => '999999.99',
      '#step' => '0.01',
      '#required' => TRUE,
    ];
    $form['frequency'] = [
      '#type' => 'number',
      '#title' => $this->t('Intervalo em meses'),
      '#min' => 1,
      '#max' => 12,
      '#step' => 1,
      '#default_value' => 1,
      '#required' => TRUE,
    ];
    $form['plan_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID do plano para atualizar (opcional)'),
      '#description' => $this->t('Deixe vazio para criar um novo plano. O ID pode ser copiado da lista abaixo.'),
      '#maxlength' => 64,
    ];
    $form['gateway_id'] = ['#type' => 'hidden', '#value' => $gateway->id()];
    $form['intent_id'] = [
      '#type' => 'hidden',
      '#value' => $form_state->get('mercadopago_plan_intent') ?? $this->newIntent(),
    ];
    $form_state->set('mercadopago_plan_intent', $form['intent_id']['#value']);
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar plano')];

    try {
      $plans = $this->recurrence->listarPlanos($gateway, 50, 0)['results'];
      $rows = [];
      foreach ($plans as $plan) {
        $rows[] = [
          (string) ($plan->id ?? ''),
          (string) ($plan->reason ?? ''),
          (string) ($plan->status ?? ''),
          (string) ($plan->auto_recurring->transaction_amount ?? ''),
          (string) ($plan->auto_recurring->currency_id ?? ''),
        ];
      }
      $form['plans'] = [
        '#type' => 'table',
        '#caption' => $this->t('Planos cadastrados no Mercado Pago'),
        '#header' => [$this->t('ID'), $this->t('Nome'), $this->t('Situação'), $this->t('Valor'), $this->t('Moeda')],
        '#rows' => $rows,
        '#empty' => $this->t('Ainda não há planos cadastrados.'),
      ];
    }
    catch (\Throwable) {
      $form['plans_error'] = ['#markup' => $this->t('Não foi possível consultar os planos agora. Tente novamente em instantes.')];
    }

    return $form;
  }

  /**
   * {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $reason = trim((string) $form_state->getValue('reason'));
    if ($reason === '' || mb_strlen($reason) > 120) {
      $form_state->setErrorByName('reason', $this->t('Informe um nome de até 120 caracteres.'));
    }
    $intent = (string) $form_state->getValue('intent_id');
    if (preg_match('/^[A-Za-z0-9_-]{20,64}$/D', $intent) !== 1 || !hash_equals((string) $form_state->get('mercadopago_plan_intent'), $intent)) {
      $form_state->setErrorByName('intent_id', $this->t('Recarregue o formulário antes de salvar o plano.'));
    }
    $plan_id = trim((string) $form_state->getValue('plan_id'));
    if ($plan_id !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $plan_id) !== 1) {
      $form_state->setErrorByName('plan_id', $this->t('O identificador do plano é inválido.'));
    }
    $amount = filter_var($form_state->getValue('amount'), FILTER_VALIDATE_FLOAT);
    if ($amount === FALSE || $amount < 0.01 || $amount > 999999.99) {
      $form_state->setErrorByName('amount', $this->t('Informe um valor entre R$ 0,01 e R$ 999.999,99.'));
    }
    $frequency = filter_var($form_state->getValue('frequency'), FILTER_VALIDATE_INT);
    if ($frequency === FALSE || $frequency < 1 || $frequency > 12) {
      $form_state->setErrorByName('frequency', $this->t('O intervalo deve ser de 1 a 12 meses.'));
    }
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $gateway = $this->gateway((string) $form_state->getValue('gateway_id'));
    if ($gateway === NULL) {
      $this->messenger()->addError($this->t('O gateway sandbox não está disponível.'));
      return;
    }
    $frequency = (int) $form_state->getValue('frequency');
    $amount = number_format((float) $form_state->getValue('amount'), 2, '.', '');
    if ($frequency < 1 || $frequency > 12 || (float) $amount <= 0 || (float) $amount > 999999.99) {
      $this->messenger()->addError($this->t('Informe um valor e intervalo válidos.'));
      return;
    }
    $backUrl = $this->domainPurposes->routeUrl('support', 'aculta_donation.donation.default')?->toString();
    if (!is_string($backUrl) || $backUrl === '') {
      $this->messenger()->addError($this->t('A página de apoio não está disponível para retorno.'));
      return;
    }
    $data = [
      'motivo' => trim((string) $form_state->getValue('reason')),
      'valor' => $amount,
      'moeda' => 'BRL',
      'frequencia' => $frequency,
      'back_url' => $backUrl,
    ];
    $fingerprint = hash('sha256', serialize($data));
    $previousFingerprint = $form_state->get('mercadopago_plan_fingerprint');
    if (is_string($previousFingerprint) && !hash_equals($previousFingerprint, $fingerprint)) {
      $this->messenger()->addError($this->t('Há uma tentativa de criação ainda não conciliada. Reenvie exatamente os mesmos dados para evitar um plano duplicado.'));
      $form_state->setRebuild();
      return;
    }
    $form_state->set('mercadopago_plan_fingerprint', $fingerprint);
    try {
      $planId = trim((string) $form_state->getValue('plan_id'));
      if ($planId !== '') {
        $this->recurrence->alterarPlano($gateway, $planId, $data);
        $this->messenger()->addStatus($this->t('O plano foi atualizado no Mercado Pago.'));
        $form_state->set('mercadopago_plan_fingerprint', NULL);
      }
      else {
        $created = $this->recurrence->criarPlano($gateway, $data, (string) $form_state->getValue('intent_id'));
        $this->messenger()->addStatus($this->t('O plano @id foi criado no Mercado Pago.', ['@id' => $created['id']]));
        $form_state->set('mercadopago_plan_intent', $this->newIntent());
        $form_state->set('mercadopago_plan_fingerprint', NULL);
      }
    }
    catch (MercadoPagoApiException $exception) {
      $this->messenger()->addError($exception->getMessage());
      $form_state->setRebuild();
      return;
    }
    catch (\Throwable) {
      $this->messenger()->addError($this->t('Não foi possível confirmar a operação com o Mercado Pago. Reenvie os mesmos dados para conciliar a tentativa.'));
      $form_state->setRebuild();
      return;
    }
    $form_state->setRebuild();
    $form_state->setRedirect('aculta_pagamentos_mercadopago.studio_plans');
  }

  /**
   * Returns enabled Mercado Pago gateways configured for sandbox only.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface[]
   *   Enabled sandbox gateways.
   */
  private function testGateways(): array {
    $entities = $this->entityTypeManager
      ->getStorage('commerce_payment_gateway')
      ->loadByProperties([
        'plugin' => 'aculta_mercadopago_checkout_pro',
        'status' => TRUE,
      ]);
    return array_filter($entities, static fn (PaymentGatewayInterface $gateway): bool => $gateway->getPlugin()->getMode() === 'test');
  }

  /**
   * Resolves a still-enabled sandbox gateway by entity ID.
   *
   * @param string $id
   *   Gateway entity ID.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface|null
   *   Sandbox gateway, when found.
   */
  private function gateway(string $id): ?PaymentGatewayInterface {
    foreach ($this->testGateways() as $gateway) {
      if ($gateway->id() === $id) {
        return $gateway;
      }
    }
    return NULL;
  }

  /**
   * Returns a URL-safe unique retry token.
   */
  private function newIntent(): string {
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
  }

}
