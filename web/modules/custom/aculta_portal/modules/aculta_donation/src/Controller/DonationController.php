<?php

namespace Drupal\aculta_donation\Controller;

use Drupal\commerce_cart\CartSession;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\aculta_donation\NewOrder;
use Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow\DonationCheckoutFlow;
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
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
  protected DomainSiteManager $purposes;

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
   * @param \Drupal\aculta_portal\Domain\DomainSiteManager $purposes
   *   Gerenciador de domínio usado no checkout.
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
    DomainSiteManager $purposes,
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
  public static function create(ContainerInterface $container): static {
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
      $container->get('aculta_portal.domain_site'),
    );
  }

  /**
   * Builds the initial presentation of the form provided by the donation flow.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   Redirect to an order specific flow.
   */
  public function frontController(RouteMatchInterface $route_match): TrustedRedirectResponse {
    $config = $this->configFactory->get('aculta_donation.settings');
    $orderIds = $this->cartSession->getCartIds();
    // If the requested step is not available, both this controller and the
    // default Commerce controller redirect to the order's indicated step.
    // We need a default value in case our checkout flow has not been used.
    $stepId = 'placeholder';
    if (empty($orderIds)) {
      // Create an order to get the flow plugin.
      $rawItemType = $config->get('donate_item_type');
      $itemTypeStr = is_string($rawItemType) ? $rawItemType : 'donation';
      $order = $this->newOrders->get('single', $itemTypeStr);
      $order->save();
      $this->cartSession->addCartId((int) $order->id());
    }
    else {
      $loadedOrder = $this->entityTypeManager->getStorage('commerce_order')->load(reset($orderIds));
      if (!$loadedOrder instanceof OrderInterface) {
        $rawItemType = $config->get('donate_item_type');
        $itemTypeStr = is_string($rawItemType) ? $rawItemType : 'donation';
        $loadedOrder = $this->newOrders->get('single', $itemTypeStr);
        $loadedOrder->save();
        $this->cartSession->addCartId((int) $loadedOrder->id());
      }
      $order = $loadedOrder;
    }
    // Always start at the first available step.
    $checkoutFlow = $this->checkoutOrderManager->getCheckoutFlow($order);
    $checkoutFlowPlugin = $checkoutFlow->getPlugin();
    // There are no order parameters so CheckoutFlowBase misses the order.
    if ($checkoutFlowPlugin instanceof DonationCheckoutFlow) {
      $checkoutFlowPlugin->conditionalOrderValue($order);
      $stepId = (string) $this->checkoutOrderManager->getCheckoutStepId($order);
    }
    $parameters = [
      'commerce_order' => (string) $order->id(),
      'step' => $stepId,
    ];
    return $this->constructStepRedirect($parameters);
  }

  /**
   * Builds and processes the form provided by the order's checkout flow.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array<mixed>|\Drupal\Core\Routing\TrustedRedirectResponse
   *   The render form.
   */
  public function nextStep(RouteMatchInterface $route_match) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $route_match->getParameter('commerce_order');
    $rawRequestedStep = $route_match->getParameter('step');
    $requestedStepId = is_string($rawRequestedStep) ? $rawRequestedStep : NULL;
    $parameters = [
      'commerce_order' => (string) $order->id(),
    ];
    $stepId = (string) $this->checkoutOrderManager->getCheckoutStepId($order, $requestedStepId);
    if ($requestedStepId !== $stepId) {
      $parameters['step'] = $stepId;
      return $this->constructStepRedirect($parameters);
    }

    $checkout_flow = $this->checkoutOrderManager->getCheckoutFlow($order);
    $checkout_flow_plugin = $checkout_flow->getPlugin();

    return (array) $this->formBuilder->getForm($checkout_flow_plugin, $stepId);
  }

  /**
   * Builds and processes a quick donation flow.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   Our own redirection into the flow with order prepared.
   */
  public function quickDonation(RouteMatchInterface $route_match): TrustedRedirectResponse {
    // Create an order.
    // Go directly to payment..
    $stepId = 'payment';
    $order = $this->newOrders->get('single');
    $order->set('checkout_step', $stepId);
    $order->save();
    $orderItems = $order->getItems();
    // Should only be one.
    $donation = reset($orderItems);
    if ($donation instanceof OrderItemInterface && $donation->hasField('field_donation_amount')) {
      $amount = $route_match->getParameter('amount');
      $currencyCode = $this->currentStore->getStore()->getDefaultCurrencyCode();
      $donation->get('field_donation_amount')->setValue([
        'number' => $amount,
        'currency_code' => $currencyCode !== '' ? $currencyCode : 'BRL',
      ]);
      $donation->save();
    }
    $this->cartSession->addCartId((int) $order->id());
    $checkoutFlow = $this->checkoutOrderManager->getCheckoutFlow($order);
    $checkoutFlowPlugin = $checkoutFlow->getPlugin();
    if ($checkoutFlowPlugin instanceof DonationCheckoutFlow) {
      $checkoutFlowPlugin->conditionalOrderValue($order);
    }
    $parameters = [
      'commerce_order' => (string) $order->id(),
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
      /** @var \Drupal\commerce_order\Entity\OrderInterface|null $order */
      $order = $route_match->getParameter('commerce_order');
      if ($order === NULL) {
        return AccessResult::forbidden();
      }
      $stateItem = $order->getState();
      if ($stateItem->getId() === 'canceled') {
        return AccessResult::forbidden()->addCacheableDependency($order);
      }
      // The user can check out only their own non-empty orders.
      if ($account->isAuthenticated()) {
        $customer_check = (string) $account->id() === (string) $order->getCustomerId();
      }
      else {
        $active_cart = $this->cartSession->hasCartId(
          (int) $order->id(),
          CartSession::ACTIVE
        );
        $completed_cart = $this->cartSession->hasCartId(
          (int) $order->id(),
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
   * @param array<string, mixed> $parameters
   *   The url parameters.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   The prepared redirect.
   */
  protected function constructStepRedirect(array $parameters): TrustedRedirectResponse {
    // Etapas de checkout (Commerce) no domínio principal; o apoio só escolhe o valor.
    $url = $this->purposes->routeUrl(SiteKey::Main, 'aculta_donation.donation_controller_formPage', $parameters)
      ?? Url::fromRoute('aculta_donation.donation_controller_formPage', $parameters);
    $request = $this->requestStack->getCurrentRequest();
    if ($request !== NULL && $request->query->has('donate_return')) {
      $url->setOption('query', ['donate_return' => $request->query->get('donate_return')]);
    }
    return new TrustedRedirectResponse($url->toString());
  }

}
