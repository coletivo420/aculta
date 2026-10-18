<?php

namespace Drupal\aculta_donation\Controller;

use Drupal\commerce_cart\CartSession;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\aculta_donation\NewOrder;
use Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow\DonationCheckoutFlow;
use Drupal\aculta_donation\Routing\DonationSecuredRedirectResponse;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for /donate and  donation customized checkout.
 */
class DonationController implements ContainerInjectionInterface {

  /**
   * Drupal\commerce_checkout\CheckoutOrderManagerInterface definition.
   *
   * @var \Drupal\commerce_checkout\CheckoutOrderManagerInterface
   */
  protected $checkoutOrderManager;

  /**
   * Drupal\Core\Form\FormBuilderInterface definition.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Injected service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * The cart session is used for access to the order on the thank you pane.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  protected $cartSession;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Injected config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Gerenciador de domínio: as etapas de checkout ficam no domínio principal (rotas do Commerce).
   */
  protected DomainPurposeManager $purposes;

  /**
   * Injected service.
   *
   * @var \Drupal\aculta_donation\NewOrder
   */
  protected $newOrders;

  /**
   * DonationController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   Injected service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Injected service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Injected service.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   Injected service.
   * @param \Drupal\commerce_checkout\CheckoutOrderManagerInterface $commerce_order_manager
   *   Injected service.
   * @param \Drupal\commerce_cart\CartSessionInterface $session
   *   Injected service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Injected service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Injected service.
   * @param \Drupal\aculta_donation\NewOrder $newOrder
   *   Injected service.
   */
  final public function __construct(
    FormBuilderInterface $form_builder,
    AccountInterface $current_user,
    RequestStack $request_stack,
    CurrentStoreInterface $current_store,
    CheckoutOrderManagerInterface $commerce_order_manager,
    CartSessionInterface $session,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $configFactory,
    NewOrder $newOrder,
    DomainPurposeManager $purposes,
  ) {
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->currentStore = $current_store;
    $this->checkoutOrderManager = $commerce_order_manager;
    $this->cartSession = $session;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $configFactory;
    $this->newOrders = $newOrder;
    $this->purposes = $purposes;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('commerce_store.current_store'),
      $container->get('commerce_checkout.checkout_order_manager'),
      $container->get('commerce_cart.cart_session'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('aculta_donation.new_order'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * Builds the initial presentation of the form provided by the donation flow.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\aculta_donation\Routing\DonationSecuredRedirectResponse
   *   Redirect to an order specific flow.
   */
  public function frontController(RouteMatchInterface $route_match) {
    $config = $this->configFactory->get('aculta_donation.settings');
    $orderIds = $this->cartSession->getCartIds();
    // If the requested step is not available, both this controller and the
    // default Commerce controller redirect to the order's indicated step.
    // We need a default value in case our checkout flow has not been used.
    $stepId = 'placeholder';
    if (empty($orderIds)) {
      // Create an order to get the flow plugin.
      $order = $this->newOrders->get('single', $config->get('donate_item_type') ?? 'donation');
      $order->save();
      $this->cartSession->addCartId($order->id());
    }
    else {
      $order = $this->entityTypeManager->getStorage('commerce_order')->load(reset($orderIds));
    }
    // Always start at the first available step.
    $checkoutFlow = $this->checkoutOrderManager->getCheckoutFlow($order);
    $checkoutFlowPlugin = $checkoutFlow->getPlugin();
    // There are no order parameters so CheckoutFlowBase misses the order.
    if ($checkoutFlowPlugin instanceof DonationCheckoutFlow) {
      $checkoutFlowPlugin->conditionalOrderValue($order);
      $stepId = $this->checkoutOrderManager->getCheckoutStepId($order);
    }
    $parameters = [
      'commerce_order' => $order->id(),
      'step' => $stepId,
    ];
    return $this->constructStepRedirect($parameters);
  }

  /**
   * Starts a monthly donation: a new order of the assinatura type, with the amount chosen before.
   *
   * O valor vem de `nivel` (chave de valor mensal do widget, por exemplo level_2) ou de `valor` (valor digitado). Sem
   * valor válido, volta à entrada de doação avulsa.
   */
  public function monthlyEntry(): RedirectResponse {
    $query = $this->requestStack->getCurrentRequest()->query;
    $amount = $this->monthlyAmount($query->get('nivel'), $query->get('valor'));
    if ($amount <= 0) {
      return new RedirectResponse(Url::fromRoute('aculta_donation.donation.default')->toString());
    }

    $config = $this->configFactory->get('aculta_donation.settings');
    // NewOrder cria o pedido do tipo assinatura para doação mensal e um item de doação com valor zero.
    $order = $this->newOrders->get('recurring', $config->get('donate_item_type') ?? 'donation');
    // O pedido precisa de id antes de os itens serem salvos com o valor escolhido.
    $order->save();
    foreach ($order->getItems() as $item) {
      $item->setUnitPrice(new Price((string) $amount, $order->getStore()->getDefaultCurrencyCode()));
      $item->save();
    }
    $order->save();
    $this->cartSession->addCartId($order->id());

    $stepId = 'placeholder';
    $checkoutFlowPlugin = $this->checkoutOrderManager->getCheckoutFlow($order)->getPlugin();
    if ($checkoutFlowPlugin instanceof DonationCheckoutFlow) {
      $checkoutFlowPlugin->conditionalOrderValue($order);
      $stepId = $this->checkoutOrderManager->getCheckoutStepId($order);
    }
    return $this->constructStepRedirect(['commerce_order' => $order->id(), 'step' => $stepId]);
  }

  /**
   * Valor mensal em reais: o valor digitado, ou o valor do nível no widget de doação mensal.
   */
  private function monthlyAmount(mixed $level, mixed $custom): int {
    if (is_numeric($custom) && (float) $custom > 0) {
      return (int) round((float) $custom);
    }
    if (!is_string($level) || $level === '') {
      return 0;
    }
    $display = $this->entityTypeManager->getStorage('entity_form_display')->load('commerce_order_item.donation.donation');
    $levels = $display?->getComponent('field_donation_amount')['settings']['levels']['monthly'] ?? [];
    return isset($levels[$level]) && is_numeric($levels[$level]) ? (int) $levels[$level] : 0;
  }

  /**
   * Builds and processes the form provided by the order's checkout flow.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array|\Drupal\aculta_donation\Routing\DonationSecuredRedirectResponse
   *   The render form.
   */
  public function nextStep(RouteMatchInterface $route_match) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $route_match->getParameter('commerce_order');
    $requestedStepId = $route_match->getParameter('step');
    $parameters = [
      'commerce_order' => $order->id(),
    ];
    $stepId = $this->checkoutOrderManager->getCheckoutStepId($order, $requestedStepId);
    if ($requestedStepId != $stepId) {
      $parameters['step'] = $stepId;
      return $this->constructStepRedirect($parameters);
    }

    $checkout_flow = $this->checkoutOrderManager->getCheckoutFlow($order);
    $checkout_flow_plugin = $checkout_flow->getPlugin();

    return $this->formBuilder->getForm($checkout_flow_plugin, $stepId);
  }

  /**
   * Builds and processes a quick donation flow.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\aculta_donation\Routing\DonationSecuredRedirectResponse
   *   Our own redirection into the flow with order prepared.
   */
  public function quickDonation(RouteMatchInterface $route_match) {
    // Create an order.
    // Go directly to payment..
    $stepId = 'payment';
    $order = $this->newOrders->get('single');
    $order->set('checkout_step', $stepId);
    $order->save();
    $orderItems = $order->getItems();
    // Should only be one.
    $donation = reset($orderItems);
    $donationAmount = $donation->field_donation_amount;
    $amount = $route_match->getParameter('amount');
    $donationAmount->setValue([
      'number' => $amount,
      'currency_code' => $this->currentStore->getStore()
        ->getDefaultCurrencyCode(),
    ]);
    $donation->save();
    $this->cartSession->addCartId($order->id());
    $checkoutFlow = $this->checkoutOrderManager->getCheckoutFlow($order);
    $checkoutFlowPlugin = $checkoutFlow->getPlugin();
    if ($checkoutFlowPlugin instanceof DonationCheckoutFlow) {
      $checkoutFlowPlugin->conditionalOrderValue($order);
    }
    $parameters = [
      'commerce_order' => $order->id(),
      'step' => $stepId,
    ];
    return $this->constructStepRedirect($parameters);
  }

  /**
   * Checks access for the donation flow.
   *
   * Assigned as custom access for route
   * `aculta_donation.donation_controller_formPage`.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Drupal route match service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(
    RouteMatchInterface $route_match,
    AccountInterface $account,
  ) {
    $config = $this->configFactory->get('aculta_donation.settings');
    $use_route = $config->get('donation_route');
    $route_check = $use_route === DonationSettingsForm::ROTA_DONATE || $use_route === DonationSettingsForm::ROTA_AMBAS;

    $details_check = AccessResult::allowed();
    if ($route_match->getRouteName() === 'aculta_donation.donation_controller_formPage') {
      // Check existing order characteristics.
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $route_match->getParameter('commerce_order');
      if ($order->getState()->value == 'canceled') {
        return AccessResult::forbidden()->addCacheableDependency($order);
      }// The user can check out only their own non-empty orders.
      if ($account->isAuthenticated()) {
        $customer_check = $account->id() == $order->getCustomerId();
      }
      else {
        $active_cart = $this->cartSession->hasCartId(
          $order->id(),
          CartSession::ACTIVE
        );
        $completed_cart = $this->cartSession->hasCartId(
          $order->id(),
          CartSession::COMPLETED
        );
        $customer_check = $active_cart || $completed_cart;
      }
      $details_check = AccessResult::allowedIf($customer_check)
        ->andIf(AccessResult::allowedIf($order->hasItems()))
        ->addCacheableDependency($order);
    }

    $access = AccessResult::allowedIf($route_check)
      ->andIf($details_check)
      ->andIf(
        AccessResult::allowedIfHasPermission($account, 'make donation')
      );

    return $access;
  }

  /**
   * Utility method to build a redirect.
   *
   * @param array $parameters
   *   The url parameters.
   *
   * @return \Drupal\aculta_donation\Routing\DonationSecuredRedirectResponse
   *   The prepared redirect.
   */
  protected function constructStepRedirect(array $parameters) {
    // Etapas de checkout (Commerce) no domínio principal; o apoio só escolhe o valor.
    $url = $this->purposes->routeUrl('main', 'aculta_donation.donation_controller_formPage', $parameters)
      ?? Url::fromRoute('aculta_donation.donation_controller_formPage', $parameters);
    $query = $this->requestStack->getCurrentRequest()->query;
    if ($query->has('donate_return')) {
      $url->setOption('query', ['donate_return' => $query->get('donate_return')]);
    }
    return new TrustedRedirectResponse($url->toString());
  }

}
