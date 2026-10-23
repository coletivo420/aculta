<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutContextRegistry;
use Drupal\aculta_portal\Checkout\CheckoutPolicyManager;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Aba Comum: chave global do checkout comum e diagnóstico transversal dos contextos.
 *
 * Mostra o que cada provider declara e os conflitos entre eles. Não cria carrinho nem pedido agregado e não exibe
 * credenciais do gateway.
 */
final class CheckoutCommonForm extends ConfigFormBase {

  private CheckoutContextRegistry $registry;

  private CheckoutPolicyManager $policy;

  private EntityTypeManagerInterface $entityTypeManager;

  private RouteBuilderInterface $routeBuilder;

  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->registry = $container->get('aculta_portal.checkout_context_registry');
    $instance->policy = $container->get('aculta_portal.checkout_policy_manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->routeBuilder = $container->get('router.builder');
    return $instance;
  }

  public function getFormId(): string {
    return 'aculta_portal_checkout_common';
  }

  protected function getEditableConfigNames(): array {
    return [CheckoutSettings::CONFIG];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $habilitado = (new CheckoutSettings($this->configFactory()))->commonCheckoutEnabled();
    $form['common_checkout_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Checkout comum do Commerce habilitado'),
      '#description' => $this->t('Desligado, as rotas globais de carrinho e de checkout do Commerce ficam fechadas para todos os contextos (Loja, LMS e Doações pelo carrinho), como no modo "donate" de Doações. O checkout dedicado de Doações não depende desta chave.'),
      '#default_value' => $habilitado,
    ];
    $form['rotas'] = [
      '#markup' => $this->t('Rotas globais do Commerce agora: @estado.', [
        '@estado' => $this->policy->commonCheckoutRoutesAvailable() ? $this->t('abertas') : $this->t('fechadas'),
      ]),
    ];
    $linhas = [];
    foreach ($this->registry->all() as $id => $provider) {
      $c = $provider->configuration();
      $linhas[] = [
        (string) $provider->label(),
        $c->mode->value,
        implode(', ', $c->orderTypeIds),
        implode(', ', $c->checkoutFlowIds),
        $c->gatesCommonCheckout ? $this->t('Sim') : $this->t('Não'),
      ];
    }
    $form['contextos'] = [
      '#type' => 'table',
      '#caption' => $this->t('Contextos de checkout registrados'),
      '#header' => [$this->t('Contexto'), $this->t('Modalidade'), $this->t('Tipos de pedido'), $this->t('Checkout flows'), $this->t('Controla as rotas globais')],
      '#rows' => $linhas,
      '#empty' => $this->t('Nenhum contexto registrado.'),
    ];
    $conflitos = $this->policy->orderTypeConflicts();
    $form['conflitos'] = $conflitos === []
      ? ['#markup' => $this->t('Sem conflitos de tipo de pedido entre contextos.')]
      : [
        '#theme' => 'item_list',
        '#title' => $this->t('Conflitos de tipo de pedido'),
        '#items' => array_map(fn(string $tipo, array $ids): string => $tipo . ': ' . implode(', ', $ids), array_keys($conflitos), $conflitos),
      ];
    $form['gateways'] = [
      '#type' => 'table',
      '#caption' => $this->t('Gateways de pagamento compartilhados (somente referência)'),
      '#header' => [$this->t('Gateway'), $this->t('Plugin'), $this->t('Ativo')],
      '#rows' => $this->gatewayRows(),
      '#empty' => $this->t('Nenhum gateway configurado.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(CheckoutSettings::CONFIG)->set('common_checkout_enabled', (bool) $form_state->getValue('common_checkout_enabled'))->save();
    $this->routeBuilder->setRebuildNeeded();
    parent::submitForm($form, $form_state);
  }

  /**
   * Nome, plugin e estado dos gateways, sem nenhum valor de configuração.
   *
   * @return list<array<int, mixed>>
   */
  private function gatewayRows(): array {
    if (!$this->entityTypeManager->hasDefinition('commerce_payment_gateway')) {
      return [];
    }
    $linhas = [];
    foreach ($this->entityTypeManager->getStorage('commerce_payment_gateway')->loadMultiple() as $gateway) {
      $linhas[] = [(string) $gateway->label(), $gateway->getPluginId(), $gateway->status() ? $this->t('Sim') : $this->t('Não')];
    }
    return $linhas;
  }

}
