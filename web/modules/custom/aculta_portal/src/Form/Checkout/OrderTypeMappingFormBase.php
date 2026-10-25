<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutMappingValidator;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base das abas Loja e LMS: tipo de pedido do contexto e checkout flow nativo desse tipo.
 *
 * O tipo de pedido é guardado em `aculta_portal.checkout_settings`. O flow continua sendo a configuração nativa do
 * Commerce (`commerce_checkout`, no tipo de pedido): esta tela só a edita, como o formulário de Doações já faz.
 */
abstract class OrderTypeMappingFormBase extends ConfigFormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  protected CheckoutMappingValidator $validator;

  protected OrderTypeCheckoutFlows $flows;

  protected RouteBuilderInterface $routeBuilder;

  protected LoggerInterface $logger;

  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->validator = $container->get('aculta_portal.checkout_mapping_validator');
    $instance->flows = $container->get('aculta_portal.order_type_checkout_flows');
    $instance->routeBuilder = $container->get('router.builder');
    $instance->logger = $container->get('logger.factory')->get('aculta_portal_checkout');
    return $instance;
  }

  /** ID do contexto no registry de checkout. */
  abstract protected function contextId(): string;

  /** Chave de `aculta_portal.checkout_settings` com o tipo de pedido do contexto. */
  abstract protected function settingKey(): string;

  /** Tipo de pedido efetivo hoje. */
  abstract protected function currentOrderType(CheckoutSettings $settings): string;

  abstract protected function intro(): string;

  protected function getEditableConfigNames(): array {
    return [CheckoutSettings::CONFIG];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $tipoAtual = $this->currentOrderType(new CheckoutSettings($this->configFactory()));
    $form['introducao'] = ['#plain_text' => $this->intro()];
    $form['order_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Tipo de pedido'),
      '#options' => $this->options('commerce_order_type'),
      '#default_value' => $tipoAtual,
      '#required' => TRUE,
    ];
    $form['checkout_flow'] = [
      '#type' => 'select',
      '#title' => $this->t('Checkout flow do tipo de pedido'),
      '#description' => $this->t('É a configuração nativa do Commerce para este tipo de pedido. Alterar aqui altera o mesmo valor no tipo de pedido.'),
      '#options' => $this->options('commerce_checkout_flow'),
      '#default_value' => $this->flows->flowId($tipoAtual),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $tipo = (string) $form_state->getValue('order_type');
    $conflitos = $this->validator->conflictingContexts($this->contextId(), $tipo);
    if ($conflitos !== []) {
      $form_state->setErrorByName('order_type', $this->t('O tipo de pedido "@tipo" já é usado por outro contexto (@contextos). Cada tipo de pedido pertence a uma única experiência.', [
        '@tipo' => $tipo,
        '@contextos' => implode(', ', $conflitos),
      ]));
    }
    if (!isset($this->options('commerce_checkout_flow')[(string) $form_state->getValue('checkout_flow')])) {
      $form_state->setErrorByName('checkout_flow', $this->t('Escolha um checkout flow existente.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tipo = (string) $form_state->getValue('order_type');
    $anterior = $this->currentOrderType(new CheckoutSettings($this->configFactory()));
    $this->config(CheckoutSettings::CONFIG)->set($this->settingKey(), $tipo)->save();
    $tipoDePedido = $this->entityTypeManager->getStorage('commerce_order_type')->load($tipo);
    $tipoDePedido?->setThirdPartySetting('commerce_checkout', 'checkout_flow', (string) $form_state->getValue('checkout_flow'));
    $tipoDePedido?->save();
    $this->routeBuilder->setRebuildNeeded();
    // Auditoria sem segredos: contexto, valores antes e depois e a conta que alterou.
    $this->logger->notice('Checkout @contexto alterado: tipo de pedido @de para @para, checkout flow @flow (uid @uid).', [
      '@contexto' => $this->contextId(),
      '@de' => $anterior,
      '@para' => $tipo,
      '@flow' => (string) $form_state->getValue('checkout_flow'),
      '@uid' => $this->currentUser()->id(),
    ]);
    parent::submitForm($form, $form_state);
  }

  /**
   * @return array<string, string>
   */
  private function options(string $entityTypeId): array {
    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return [];
    }
    $opcoes = [];
    foreach ($this->entityTypeManager->getStorage($entityTypeId)->loadMultiple() as $id => $entidade) {
      $opcoes[(string) $id] = (string) $entidade->label();
    }
    asort($opcoes);
    return $opcoes;
  }

}
