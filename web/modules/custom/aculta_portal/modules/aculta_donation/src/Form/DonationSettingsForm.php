<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Form;

use Drupal\commerce\EntityHelper;
use Drupal\commerce_checkout\Entity\CheckoutFlowInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fluxo de doação: qual rota usa qual tipo de item, tipo de pedido e checkout.
 *
 * Port de commerce_donation_flow (DonationSettingsForm), com as correções do port: a chave do tipo de pedido do /donate
 * agora é a mesma gravada (donate_order_type), o teste de "dois caminhos" não usa empty() sobre uma expressão e os
 * campos obrigatórios só são exigidos quando visíveis. A configuração pertence a aculta_donation.settings.
 */
final class DonationSettingsForm extends ConfigFormBase {

  /** Só a rota /donate (doação dedicada). */
  public const ROTA_DONATE = 'donate';

  /** Só a rota /cart e /checkout padrão (doação como item da loja). */
  public const ROTA_CART = 'cart';

  /** As duas rotas. Exige tipos de item e de pedido diferentes. */
  public const ROTA_AMBAS = 'both';

  protected EntityTypeManagerInterface $entityTypeManager;

  protected RouteBuilderInterface $routeBuilder;

  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->routeBuilder = $container->get('router.builder');
    return $instance;
  }

  public function getFormId(): string {
    return 'aculta_donation_fluxo_form';
  }

  protected function getEditableConfigNames(): array {
    return ['aculta_donation.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_donation.settings');
    $tiposDePedido = EntityHelper::extractLabels($this->entityTypeManager->getStorage('commerce_order_type')->loadMultiple());
    $tiposDeItem = EntityHelper::extractLabels($this->entityTypeManager->getStorage('commerce_order_item_type')->loadMultiple());
    $fluxos = $this->entityTypeManager->getStorage('commerce_checkout_flow')->loadMultiple();
    $fluxosDeDoacao = $this->filtrarFluxos($fluxos, TRUE);
    $fluxosDaLoja = $this->filtrarFluxos($fluxos, FALSE);
    $dois = count($tiposDePedido) >= 2 && count($tiposDeItem) >= 2;

    $form['resumo'] = [
      '#type' => 'item',
      '#markup' => $this->t('Escolha como a doação entra no site. Doações e assinaturas não podem ser estornadas; por isso o pagamento de doação não oferece estorno.'),
    ];

    $rotas = [
      self::ROTA_DONATE => $this->t('Somente a rota /donate (doação dedicada)'),
      self::ROTA_CART => $this->t('Somente o carrinho e o checkout padrão (doação como item da loja)'),
    ];
    // "As duas rotas" só existe com tipos de item e de pedido distintos; sem eles a opção não é oferecida.
    if ($dois) {
      $rotas[self::ROTA_AMBAS] = $this->t('As duas rotas');
    }
    $form['donation_route'] = [
      '#type' => 'radios',
      '#title' => $this->t('Como a doação entra no site'),
      '#default_value' => $config->get('donation_route') ?? self::ROTA_DONATE,
      '#options' => $rotas,
      '#description' => $this->t('"As duas rotas" exige um tipo de item e um tipo de pedido diferentes para cada rota.'),
    ];

    $form['rota_donate'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rota /donate'),
      '#states' => [
        'visible' => [
          [':input[name="donation_route"]' => ['value' => self::ROTA_DONATE]],
          [':input[name="donation_route"]' => ['value' => self::ROTA_AMBAS]],
        ],
      ],
    ];
    $form['rota_donate']['donate_item_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Tipo de item do pedido usado em /donate'),
      '#default_value' => $config->get('donate_item_type') ?? 'donation',
      '#options' => $tiposDeItem,
      '#states' => ['required' => [':input[name="donation_route"]' => ['!value' => self::ROTA_CART]]],
    ];
    $form['rota_donate']['donate_order_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Tipo de pedido usado com esse item'),
      '#default_value' => $config->get('donate_order_type') ?? 'default',
      '#options' => $tiposDePedido,
    ];
    $form['rota_donate']['donate_checkout_flow'] = [
      '#type' => 'select',
      '#title' => $this->t('Fluxo de checkout da doação'),
      '#options' => $fluxosDeDoacao,
      '#empty_option' => $this->t('- Escolha um fluxo -'),
      '#default_value' => $config->get('donate_checkout_flow') ?? 'doacao',
      '#description' => $this->t('O fluxo precisa usar o plugin de doação (fluxo_doacao).'),
      '#states' => ['required' => [':input[name="donation_route"]' => ['!value' => self::ROTA_CART]]],
    ];

    $form['rota_cart'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Carrinho e checkout padrão'),
      '#states' => [
        'visible' => [
          [':input[name="donation_route"]' => ['value' => self::ROTA_CART]],
          [':input[name="donation_route"]' => ['value' => self::ROTA_AMBAS]],
        ],
      ],
    ];
    $form['rota_cart']['cart_item_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Tipo de item do pedido usado no carrinho'),
      '#default_value' => $config->get('cart_item_type') ?? 'donation',
      '#options' => $tiposDeItem,
      '#states' => ['required' => [':input[name="donation_route"]' => ['!value' => self::ROTA_DONATE]]],
    ];
    $form['rota_cart']['cart_order_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Tipo de pedido usado com esse item'),
      '#default_value' => $config->get('cart_order_type') ?? 'default',
      '#options' => $tiposDePedido,
    ];
    $form['rota_cart']['cart_checkout_flow'] = [
      '#type' => 'select',
      '#title' => $this->t('Fluxo de checkout do carrinho'),
      '#options' => $fluxosDaLoja,
      '#empty_option' => $this->t('- Escolha um fluxo -'),
      '#default_value' => $config->get('cart_checkout_flow') ?? 'donation_multistep_flow',
      '#description' => $this->t('O fluxo precisa de etapas próprias para configurar a doação, ou um processo separado.'),
      '#states' => ['required' => [':input[name="donation_route"]' => ['!value' => self::ROTA_DONATE]]],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $rota = $form_state->getValue('donation_route');
    if ($rota === self::ROTA_DONATE) {
      $this->limparRotaCart($form_state);
    }
    elseif ($rota === self::ROTA_CART) {
      $this->limparRotaDonate($form_state);
    }
    elseif ($rota === self::ROTA_AMBAS) {
      if ($form_state->getValue('donate_order_type') === $form_state->getValue('cart_order_type')) {
        $erro = $this->t('As rotas /donate e /cart precisam de tipos de pedido diferentes.');
        $form_state->setErrorByName('donate_order_type', $erro);
        $form_state->setErrorByName('cart_order_type', $erro);
      }
    }
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $valores = $form_state->getValues();
    $config = $this->config('aculta_donation.settings');
    foreach (['donation_route', 'donate_item_type', 'donate_order_type', 'donate_checkout_flow', 'cart_item_type', 'cart_order_type', 'cart_checkout_flow'] as $chave) {
      $config->set($chave, $valores[$chave] ?? NULL);
    }
    $config->save();

    // O fluxo fica no tipo de pedido, e o tipo de item fica ligado ao tipo de pedido escolhido.
    foreach (['donate' => 'donate', 'cart' => 'cart'] as $rota => $prefixo) {
      $tipoDePedido = $valores[$prefixo . '_order_type'] ?? NULL;
      $tipoDeItem = $valores[$prefixo . '_item_type'] ?? NULL;
      $fluxo = $valores[$prefixo . '_checkout_flow'] ?? NULL;
      if ($tipoDePedido && $fluxo) {
        $pedido = $this->entityTypeManager->getStorage('commerce_order_type')->load($tipoDePedido);
        $pedido?->setThirdPartySetting('commerce_checkout', 'checkout_flow', $fluxo);
        $pedido?->save();
      }
      if ($tipoDePedido && $tipoDeItem) {
        $item = $this->entityTypeManager->getStorage('commerce_order_item_type')->load($tipoDeItem);
        $item?->setOrderTypeId($tipoDePedido);
        $item?->save();
      }
    }

    $this->routeBuilder->setRebuildNeeded();
    parent::submitForm($form, $form_state);
  }

  /** Lista os fluxos de doação (TRUE) ou da loja (FALSE), com rótulos. */
  private function filtrarFluxos(array $fluxos, bool $doacao): array {
    $filtrados = array_filter(
      $fluxos,
      static fn(CheckoutFlowInterface $fluxo): bool => str_starts_with($fluxo->getPluginId(), 'donation_checkout') === $doacao,
    );
    return EntityHelper::extractLabels($filtrados);
  }

  private function limparRotaCart(FormStateInterface $form_state): void {
    foreach (['cart_item_type', 'cart_order_type', 'cart_checkout_flow'] as $chave) {
      $form_state->setValue($chave, NULL);
    }
  }

  private function limparRotaDonate(FormStateInterface $form_state): void {
    foreach (['donate_item_type', 'donate_order_type', 'donate_checkout_flow'] as $chave) {
      $form_state->setValue($chave, NULL);
    }
  }

}
