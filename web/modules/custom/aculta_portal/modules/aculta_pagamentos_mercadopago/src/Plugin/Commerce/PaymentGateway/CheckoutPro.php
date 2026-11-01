<?php

namespace Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway;

use Drupal\profile\Entity\ProfileInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\PaymentMethodStorageInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\aculta_pagamentos_mercadopago\Event\RefundEligibilityEvent;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoAmount;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\commerce_price\Price;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\MercadoPagoConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Mercado Pago Checkout payment gateway.
 */
#[CommercePaymentGateway(
  id: 'aculta_mercadopago_checkout_pro',
  label: new TranslatableMarkup('Mercado Pago CheckoutPro'),
  display_label: new TranslatableMarkup('Mercado Pago'),
  modes: [
    'test' => new TranslatableMarkup('Sandbox - Test account'),
    'stage' => new TranslatableMarkup('Stage - Production account & Test credentials'),
    'live' => new TranslatableMarkup('Live - Production account & Production credentials'),
  ],
  forms: [
    'offsite-payment' => 'Drupal\\aculta_pagamentos_mercadopago\\PluginForm\\Checkout\\RedirectCheckoutForm',
  ],
  payment_method_types: ['aculta_mercadopago_checkout_pro'],
  credit_card_types: ['amex', 'mastercard', 'visa'],
  payment_type: 'aculta_mercadopago',
)]
class CheckoutPro extends OffsitePaymentGatewayBase implements SupportsRefundsInterface {

  /**
   * Mercado Pago Integrator ID for integration attribution.
   */
  public const INTEGRATOR_ID = 'dev_41f92bb906be11f1865d6a6135317fac';

  /**
   * Payment type IDs shown as one "Card brands" block.
   */
  protected const CARD_PAYMENT_TYPE_IDS = ['credit_card', 'debit_card', 'prepaid_card'];

  /**
   * Form key for the unified card brands block (Credit + Debit + Prepaid).
   */
  protected const CARD_BRANDS_FORM_KEY = 'card_brands';

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;


  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The event dispatcher, used to ask subscribers whether a refund is allowed.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Lock backend used to serialize payment notifications by remote payment ID.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * Factory for SDK clients configured with this gateway's active credentials.
   *
   * @var \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory
   */
  protected MercadoPagoClientFactory $clientFactory;

  /**
   * {@inheritdoc}
   *
   * Constructor receives only configuration, plugin_id, and plugin_definition.
   * Other dependencies are assigned in PaymentGatewayBase::create().
   *
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array<string, mixed> $plugin_definition
   *   The plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $token = $this->getAccessToken();
    if (!empty($token)) {
      MercadoPagoConfig::setAccessToken($token);
    }
    MercadoPagoConfig::setIntegratorId(self::INTEGRATOR_ID);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array<string, mixed> $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->requestStack = $container->get('request_stack');
    $instance->loggerFactory = $container->get('logger.factory');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->lock = $container->get('lock');
    $instance->clientFactory = $container->get('aculta_pagamentos_mercadopago.client_factory');
    return $instance;
  }

  /**
   * Re-initializes the SDK after the plugin is unserialized.
   */
  public function __wakeup(): void {
    $token = $this->getAccessToken();
    if (!empty($token)) {
      MercadoPagoConfig::setAccessToken($token);
    }
  }

  /**
   * Returns the Access token for the current mode (test, stage, or live).
   *
   * @return string
   *   The configured access token, or empty string if not set.
   */
  public function getAccessToken(): string {
    $mode = $this->getMode();
    $key = $mode === 'test' ? 'access_token_test' : ($mode === 'stage' ? 'access_token_stage' : 'access_token_prod');
    return (string) ($this->configuration[$key] ?? '');
  }

  /**
   * Returns the Public key for the current mode (test, stage, or live).
   *
   * @return string
   *   The configured public key, or empty string if not set.
   */
  public function getPublicKey(): string {
    $mode = $this->getMode();
    $key = $mode === 'test' ? 'public_key_test' : ($mode === 'stage' ? 'public_key_stage' : 'public_key_prod');
    return (string) ($this->configuration[$key] ?? '');
  }

  /**
   * {@inheritdoc}
   *
   * Returns the gateway mode with a fallback when configuration has not yet
   * been merged with defaults (e.g. during plugin construction).
   */
  public function getMode() {
    if (isset($this->configuration['mode']) && $this->configuration['mode'] !== '') {
      return $this->configuration['mode'];
    }
    $modes = $this->getSupportedModes();
    return $modes ? (string) array_key_first($modes) : 'test';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Default configuration merged with the parent gateway defaults.
   */
  public function defaultConfiguration(): array {
    return [
      'public_key_test' => '',
      'access_token_test' => '',
      'public_key_stage' => '',
      'access_token_stage' => '',
      'public_key_prod' => '',
      'access_token_prod' => '',
      'client_id' => '',
      'client_secret' => '',
      'redirect_mode' => 'redirect',
      'country' => 'BR',
      'payment_types' => [],
      'payment_method' => [],
      'max_installments' => 12,
      'autofee' => FALSE,
      'debug_logging' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The current form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The configuration form elements.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['credentials_environment'] = [
      '#type' => 'item',
      '#title' => $this->t('Credentials'),
      '#markup' => $this->t('Mercado Pago credentials are supplied by the active environment and cannot be edited here.'),
      '#weight' => -10,
    ];

    $form['redirect_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Checkout redirect mode'),
      '#description' => $this->t('The redirect mode for this checkout.'),
      '#default_value' => $this->configuration['redirect_mode'],
      '#required' => TRUE,
      '#options' => [
        'redirect' => $this->t('Redirect (default): redirects the user within the same browser window'),
        'external_redirect' => $this->t('External redirect: opens the checkout in a popup window that closes automatically when the user returns'),
      ],
    ];

    $wrapper_id = 'mercado-pago-payment-options-wrapper';
    $form['country'] = [
      '#type' => 'value',
      '#value' => 'BR',
    ];

    $form['payment_options_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    $this->buildPaymentOptionsWrapper($form['payment_options_wrapper'], $form_state);

    $form['max_installments'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum installments'),
      '#description' => $this->t('Maximum number of installments the buyer can use to pay (1–12 depending on country).'),
      '#default_value' => $this->configuration['max_installments'],
      '#min' => 1,
      '#max' => 12,
      '#required' => TRUE,
    ];

    $form['debug_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable extra debug logging'),
      '#description' => $this->t('When enabled, a sanitized Checkout Pro preference payload is logged for troubleshooting. Payer details are removed; order and item details remain. Leave disabled in production.'),
      '#default_value' => $this->configuration['debug_logging'],
    ];

    return $form;
  }

  /**
   * Fills the payment options wrapper with payment types and methods (by type).
   *
   * @param array<string, mixed> $wrapper
   *   The container element to fill (payment_options_wrapper).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state (used for current values when rebuilt via AJAX).
   */
  protected function buildPaymentOptionsWrapper(array &$wrapper, FormStateInterface $form_state): void {
    // Resolve config key as in the AJAX callback: values are stored under
    // plugin_id, not the form tree key 'form'.
    $values_config = $form_state->getValues()['configuration'] ?? [];
    $request_config = $this->requestStack->getCurrentRequest()?->request->all()['configuration'] ?? [];
    $config_key = array_key_first($values_config) ?? array_key_first($request_config) ?? $this->pluginId;
    $form_parents = ['configuration', $config_key];

    $country = (string) ($form_state->getValue(['configuration', $config_key, 'country']) ?? '');
    if ($country === '') {
      $input = $form_state->getUserInput()['configuration'][$config_key]['country'] ?? NULL;
      $country = (string) ($input ?? (($request_config[$config_key] ?? [])['country'] ?? ''));
    }
    if ($country === '') {
      $country = (string) ($this->configuration['country'] ?? '');
    }

    $config_values = array_merge(
      $this->configuration,
      $form_state->getValue($form_parents) ?? [],
      $form_state->getUserInput()['configuration'][$config_key] ?? []
    );

    $wrapper_values = $config_values['payment_options_wrapper'] ?? [];

    $selected_type_ids = [];
    if (!empty($wrapper_values['payment_types']) && is_array($wrapper_values['payment_types'])) {
      $selected_type_ids = array_keys(array_filter($wrapper_values['payment_types']));
    }
    if (empty($selected_type_ids)) {
      $selected_type_ids = array_keys($this->configuration['payment_types'] ?? []);
    }
    // Only show configurable types; always-enabled types are not in the form.
    $selected_type_ids = array_diff($selected_type_ids, MercadoPagoPaymentMethodsMap::$alwaysEnabledPaymentTypes);
    // When country changed, only keep types that exist for the new country.
    if ($country && isset(MercadoPagoPaymentMethodsMap::$methodsByCountryAndType[$country])) {
      $valid_types = array_keys(MercadoPagoPaymentMethodsMap::getConfigurablePaymentTypesForCountry($country));
      $selected_type_ids = array_intersect($selected_type_ids, $valid_types);
    }

    // Group card types as one "Card brands" block.
    $selected_card_types = array_intersect(static::CARD_PAYMENT_TYPE_IDS, $selected_type_ids);
    $other_type_ids = array_diff($selected_type_ids, static::CARD_PAYMENT_TYPE_IDS);

    $saved_payment_method = $this->configuration['payment_method'] ?? [];
    $saved_methods_per_type = (bool) (is_array($saved_payment_method) && $saved_payment_method !== [] && is_array(reset($saved_payment_method)));

    $selected_method_ids = [];
    if (!empty($wrapper_values['payment_method']) && is_array($wrapper_values['payment_method'])) {
      foreach ($wrapper_values['payment_method'] as $type_data) {
        if (!is_array($type_data)) {
          continue;
        }
        $methods_value = $type_data['methods'] ?? $type_data;
        foreach (Checkboxes::getCheckedCheckboxes(is_array($methods_value) ? $methods_value : []) as $method_id) {
          $selected_method_ids[] = $method_id;
        }
      }
    }

    // Show type options only when a country is selected.
    $type_options = [];
    if ($country && isset(MercadoPagoPaymentMethodsMap::$methodsByCountryAndType[$country])) {
      $valid_types = array_keys(MercadoPagoPaymentMethodsMap::getConfigurablePaymentTypesForCountry($country));
      foreach (MercadoPagoPaymentMethodsMap::getConfigurablePaymentTypesForCountry($country) as $type_id => $label) {
        $type_options[$type_id] = $this->getStringTranslation()->translate($label);
      }
      $normalized_types = array_combine($selected_type_ids, $selected_type_ids) ?: [];
      $form_state->setValue(array_merge($form_parents, ['payment_options_wrapper', 'payment_types']), $normalized_types);
      $by_type = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, $selected_type_ids);
      $normalized_methods = [];

      // Show one card brand set when any card type is selected. Include all
      // country card methods so AJAX type changes keep submitted values valid.
      if (!empty($selected_card_types)) {
        $by_type_all_cards = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, static::CARD_PAYMENT_TYPE_IDS);
        $valid_card_method_ids = [];
        foreach ($by_type_all_cards as $methods_for_type) {
          foreach (array_keys($methods_for_type) as $mid) {
            $valid_card_method_ids[$mid] = TRUE;
          }
        }
        $valid_card_method_ids = array_keys($valid_card_method_ids);

        $card_checked = Checkboxes::getCheckedCheckboxes($wrapper_values['payment_method'][static::CARD_BRANDS_FORM_KEY]['methods'] ?? []);
        if (empty($card_checked) && $saved_methods_per_type) {
          $card_checked = [];
          foreach (static::CARD_PAYMENT_TYPE_IDS as $tid) {
            if (isset($saved_payment_method[$tid])) {
              $card_checked = array_merge($card_checked, array_keys($saved_payment_method[$tid]));
            }
          }
          $card_checked = array_unique($card_checked);
        }
        elseif (empty($card_checked) && !empty($saved_payment_method)) {
          // Flat saved config uses method IDs as checked keys.
          // @phpstan-ignore-next-line booleanNot.alwaysTrue (configuration shape can be per-type or flat)
          if (!$saved_methods_per_type) {
            $card_checked = array_keys($saved_payment_method);
          }
        }
        $card_checked = array_values(array_intersect($card_checked, $valid_card_method_ids));
        $normalized_methods[static::CARD_BRANDS_FORM_KEY] = [
          'methods' => array_combine($card_checked, $card_checked) ?: [],
        ];
      }

      // Other types: one block per type (ticket, bank_transfer, atm, etc.).
      foreach ($other_type_ids as $type_id) {
        if (!isset($by_type[$type_id])) {
          continue;
        }
        $valid_method_ids = array_keys($by_type[$type_id]);
        $submitted_for_type = Checkboxes::getCheckedCheckboxes($wrapper_values['payment_method'][$type_id]['methods'] ?? []);
        if (empty($submitted_for_type)) {
          if ($saved_methods_per_type && isset($saved_payment_method[$type_id])) {
            $submitted_for_type = array_keys($saved_payment_method[$type_id]);
          }
          elseif (!empty($saved_payment_method)) {
            $submitted_for_type = array_intersect(array_keys($saved_payment_method), $valid_method_ids);
          }
        }
        $checked = array_intersect($submitted_for_type, $valid_method_ids);
        $normalized_methods[$type_id] = [
          'methods' => array_combine($checked, $checked) ?: [],
        ];
      }

      $form_state->setValue(array_merge($form_parents, ['payment_options_wrapper', 'payment_method']), $normalized_methods);
      $selected_method_ids = [];
      foreach ($normalized_methods as $type_data) {
        $selected_method_ids = array_merge($selected_method_ids, array_keys($type_data['methods']));
      }
    }
    else {
      $form_state->setValue(array_merge($form_parents, ['payment_options_wrapper', 'payment_types']), []);
      $form_state->setValue(array_merge($form_parents, ['payment_options_wrapper', 'payment_method']), []);
      $selected_type_ids = [];
      $selected_method_ids = [];
    }

    $wrapper['payment_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Accepted payment types'),
      '#description' => $country
        ? $this->t('Payment types to offer at checkout. Unchecked types will be excluded.') . ' ' . $this->t('Mercado Pago (account money and wallet) is always enabled and does not require configuration.')
        : $this->t('Select a country above to see available payment types.'),
      '#default_value' => $selected_type_ids,
      '#options' => $type_options,
      '#ajax' => [
        'callback' => [static::class, 'ajaxRefreshPaymentOptions'],
        'wrapper' => 'mercado-pago-payment-options-wrapper',
        'event' => 'change',
      ],
    ];

    $wrapper['payment_method'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Accepted payment methods'),
      '#description' => (!$country || empty($selected_type_ids))
        ? $this->t('Select a country and at least one payment type above to see available methods.')
        : $this->t('Unchecked methods will be excluded.'),
    ];

    if ($country && !empty($selected_type_ids)) {
      $by_type = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, $selected_type_ids);

      // Show one card brand block with the country-wide card method union. This
      // keeps AJAX values valid when card types change.
      if (!empty($selected_card_types)) {
        $by_type_all_cards = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, static::CARD_PAYMENT_TYPE_IDS);
        $card_options = [];
        foreach ($by_type_all_cards as $methods_for_type) {
          foreach ($methods_for_type as $method_id => $label) {
            if (!isset($card_options[$method_id])) {
              $card_options[$method_id] = $this->getStringTranslation()->translate($label);
            }
          }
        }
        $checked_card = isset($normalized_methods[static::CARD_BRANDS_FORM_KEY]['methods'])
          ? array_keys($normalized_methods[static::CARD_BRANDS_FORM_KEY]['methods']) : [];
        $wrapper['payment_method'][static::CARD_BRANDS_FORM_KEY] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Card brands (Credit / Debit / Prepaid card)'),
          '#description' => $this->t('Card brands available for the selected card payment types above.'),
          'methods' => [
            '#type' => 'checkboxes',
            '#options' => $card_options,
            '#default_value' => $checked_card,
          ],
        ];
      }

      // One block per other type (ticket, bank_transfer, atm, etc.).
      foreach ($other_type_ids as $type_id) {
        if (!isset($by_type[$type_id])) {
          continue;
        }
        $type_label = MercadoPagoPaymentMethodsMap::$paymentTypes[$type_id] ?? $type_id;
        $options = [];
        foreach ($by_type[$type_id] as $method_id => $label) {
          $options[$method_id] = $this->getStringTranslation()->translate($label);
        }
        $checked_for_type = isset($normalized_methods[$type_id]['methods']) ? array_keys($normalized_methods[$type_id]['methods']) : [];
        $wrapper['payment_method'][$type_id] = [
          '#type' => 'fieldset',
          '#title' => $type_label,
          'methods' => [
            '#type' => 'checkboxes',
            '#options' => $options,
            '#default_value' => $checked_for_type,
          ],
        ];
      }
    }
  }

  /**
   * AJAX callback: returns the payment options wrapper for Drupal to replace.
   *
   * Uses Form API #ajax: the callback returns the wrapper form element; Drupal
   * renders it and replaces the element with id from #ajax['wrapper'].
   * The wrapper must include the same ID in the returned HTML so the next
   * AJAX can find its target ("ID perdido" fix).
   *
   * @param array<string, mixed> $form
   *   The full form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The payment_options_wrapper form element with its wrapper ID.
   */
  public static function ajaxRefreshPaymentOptions(array $form, FormStateInterface $form_state): array {
    $wrapper_id = 'mercado-pago-payment-options-wrapper';

    // Form state stores config under plugin_id, not "form".
    $values_config = $form_state->getValues()['configuration'] ?? [];
    // getUserInput() is this AJAX request's POST; no service locator is needed.
    $user_input = $form_state->getUserInput()['configuration'] ?? [];
    $config_key = array_keys($values_config)[0] ?? array_keys($user_input)[0] ?? 'form';
    $country = (string) ($form_state->getValue(['configuration', $config_key, 'country']) ?? '');
    if ($country === '') {
      $country = (string) ($user_input[$config_key]['country'] ?? '');
    }

    // Find the wrapper in the form tree. The inline form can contain objects.
    $form_config_key = NULL;
    foreach ($form['configuration'] ?? [] as $key => $conf) {
      if (is_array($conf) && isset($conf['payment_options_wrapper'])) {
        $form_config_key = $key;
        break;
      }
    }

    if ($form_config_key !== NULL) {
      // Return the wrapper as already built and processed by the form builder
      // in this request. Rebuilding it here would skip #process, so checkboxes
      // would render empty. The wrapper was built with the new country when
      // buildConfigurationForm ran during this AJAX rebuild.
      $wrapper = &$form['configuration'][$form_config_key]['payment_options_wrapper'];
      // Ensure the wrapper has the target id so the next AJAX can find it.
      if (empty($wrapper['#prefix']) || strpos($wrapper['#prefix'], $wrapper_id) === FALSE) {
        $wrapper['#prefix'] = '<div id="' . $wrapper_id . '">';
        $wrapper['#suffix'] = '</div>';
      }
      return $wrapper;
    }

    // Fallback when wrapper not found.
    $content = $country && isset(MercadoPagoPaymentMethodsMap::$methodsByCountryAndType[$country])
      ? '<p>' . t('Select at least one payment type to see methods.') . '</p>'
      : '<p>' . t('Select a country to see payment methods.') . '</p>';
    return [
      '#markup' => '<div id="' . $wrapper_id . '">' . $content . '</div>',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @param array &$form
   *   The form array, modified by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @phpstan-ignore-next-line missingType.iterableValue (Form API passes untyped array)
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Remove card method IDs that are no longer valid for selected types.
    // This prevents invalid submitted values after an AJAX option change.
    $values = $form_state->getValue($form['#parents']);
    if (!empty($values['payment_options_wrapper']['payment_method'][static::CARD_BRANDS_FORM_KEY]['methods'])
      && !empty($values['country'])) {
      $country = (string) ($values['country'] ?? '');
      $payment_types = $values['payment_options_wrapper']['payment_types'] ?? [];
      $selected_type_ids = is_array($payment_types) ? array_keys($payment_types) : [];
      $selected_card_types = array_intersect(static::CARD_PAYMENT_TYPE_IDS, $selected_type_ids);
      if (!empty($selected_card_types) && isset(MercadoPagoPaymentMethodsMap::$methodsByCountryAndType[$country])) {
        $by_type = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, $selected_type_ids);
        $valid_card_method_ids = [];
        foreach ($selected_card_types as $tid) {
          if (isset($by_type[$tid])) {
            foreach (array_keys($by_type[$tid]) as $mid) {
              $valid_card_method_ids[$mid] = TRUE;
            }
          }
        }
        $valid_card_method_ids = array_keys($valid_card_method_ids);
        $methods = $values['payment_options_wrapper']['payment_method'][static::CARD_BRANDS_FORM_KEY]['methods'];
        $checked = is_array($methods) ? Checkboxes::getCheckedCheckboxes($methods) : [];
        $filtered = array_values(array_intersect($checked, $valid_card_method_ids));
        $values['payment_options_wrapper']['payment_method'][static::CARD_BRANDS_FORM_KEY]['methods']
          = array_combine($filtered, $filtered) ?: [];
        $form_state->setValue(array_merge($form['#parents'], ['payment_options_wrapper', 'payment_method']), $values['payment_options_wrapper']['payment_method']);
      }
    }

    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);

    $country = (string) ($values['country'] ?? 'BR');
    if ($country === '') {
      $country = 'BR';
      $form_state->setValue(array_merge($form['#parents'], ['country']), 'BR');
    }

    $max_installments = (int) ($values['max_installments'] ?? 0);
    if ($max_installments < 1 || $max_installments > 12) {
      $form_state->setError($form['max_installments'], $this->t('Maximum installments must be between 1 and 12.'));
    }

  }

  /**
   * {@inheritdoc}
   *
   * @param array &$form
   *   The form array, modified by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @phpstan-ignore-next-line missingType.iterableValue (Form API passes untyped array)
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['redirect_mode'] = $values['redirect_mode'];
      $country = $values['country'] ?? '';
      $this->configuration['country'] = $country;

      // Unchecked checkboxes are not sent by the browser, and nested form state
      // can merge with defaults and show all as checked. Use raw request so we
      // only get actually submitted (checked) options. Try both possible keys
      // under 'configuration', keyed by plugin_id or 'form'.
      $request_config = $this->requestStack->getCurrentRequest()?->request->all()['configuration'] ?? [];
      $plugin_id = $form['#parents'][1] ?? NULL;
      $wrapper_values = [];
      if (is_array($request_config)) {
        foreach ([$plugin_id, 'form'] as $config_key) {
          if ($config_key && !empty($request_config[$config_key]['payment_options_wrapper']) && is_array($request_config[$config_key]['payment_options_wrapper'])) {
            $wrapper_values = $request_config[$config_key]['payment_options_wrapper'];
            break;
          }
        }
      }
      if (empty($wrapper_values)) {
        $wrapper_values = $values['payment_options_wrapper'] ?? $values;
      }
      $wrapper_values = is_array($wrapper_values) ? $wrapper_values : [];

      $payment_types = Checkboxes::getCheckedCheckboxes($wrapper_values['payment_types'] ?? []);
      // Save only types that are valid for the selected country.
      $enabled_payment_types = [];
      if ($country && isset(MercadoPagoPaymentMethodsMap::$methodsByCountryAndType[$country])) {
        $valid_types = array_keys(MercadoPagoPaymentMethodsMap::getConfigurablePaymentTypesForCountry($country));
        foreach ($payment_types as $type_id) {
          if (in_array($type_id, $valid_types, TRUE)) {
            $enabled_payment_types[$type_id] = $type_id;
          }
        }
      }
      $this->configuration['payment_types'] = $enabled_payment_types;

      // Save payment methods per type. Card brands share one form block.
      $enabled_payment_methods_by_type = [];
      if ($country && isset(MercadoPagoPaymentMethodsMap::$methodsByCountryAndType[$country])) {
        $valid_methods_by_type = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, array_keys($enabled_payment_types));
        $request_config = $this->requestStack->getCurrentRequest()?->request->all()['configuration'] ?? [];
        $selected_card_types = array_intersect(static::CARD_PAYMENT_TYPE_IDS, array_keys($enabled_payment_types));
        $other_type_ids = array_diff(array_keys($enabled_payment_types), static::CARD_PAYMENT_TYPE_IDS);

        // Card brands: one block applies to all selected card types.
        if (!empty($selected_card_types)) {
          $card_checked = [];
          foreach ([$plugin_id, 'form'] as $config_key) {
            if (!$config_key || empty($request_config[$config_key])) {
              continue;
            }
            $path = [$config_key, 'payment_options_wrapper', 'payment_method', static::CARD_BRANDS_FORM_KEY];
            $type_data = NestedArray::getValue($request_config, $path);
            if (is_array($type_data)) {
              $methods_value = $type_data['methods'] ?? $type_data;
              $card_checked = is_array($methods_value) ? Checkboxes::getCheckedCheckboxes($methods_value) : [];
              break;
            }
          }
          if (empty($card_checked)) {
            $type_data = $wrapper_values['payment_method'][static::CARD_BRANDS_FORM_KEY] ?? [];
            $methods_value = is_array($type_data) ? ($type_data['methods'] ?? $type_data) : [];
            $card_checked = is_array($methods_value) ? Checkboxes::getCheckedCheckboxes($methods_value) : [];
          }
          // Save the selected card brands under each enabled card type so the
          // form can restore the same selection.
          foreach ($selected_card_types as $type_id) {
            $enabled_payment_methods_by_type[$type_id] = [];
            foreach ($card_checked as $method_id) {
              $enabled_payment_methods_by_type[$type_id][$method_id] = $method_id;
            }
          }
        }

        // Other types: one block per type (ticket, bank_transfer, atm, etc.).
        foreach ($other_type_ids as $type_id) {
          if (!isset($valid_methods_by_type[$type_id])) {
            continue;
          }
          $valid_method_ids = array_keys($valid_methods_by_type[$type_id]);
          $checked = [];
          foreach ([$plugin_id, 'form'] as $config_key) {
            if (!$config_key || empty($request_config[$config_key])) {
              continue;
            }
            $path = [$config_key, 'payment_options_wrapper', 'payment_method', $type_id];
            $type_data = NestedArray::getValue($request_config, $path);
            if (is_array($type_data)) {
              $methods_value = $type_data['methods'] ?? $type_data;
              $checked = is_array($methods_value) ? Checkboxes::getCheckedCheckboxes($methods_value) : [];
              break;
            }
          }
          if (empty($checked)) {
            $type_data = $wrapper_values['payment_method'][$type_id] ?? [];
            $methods_value = is_array($type_data) ? ($type_data['methods'] ?? $type_data) : [];
            $checked = is_array($methods_value) ? Checkboxes::getCheckedCheckboxes($methods_value) : [];
          }
          $enabled_payment_methods_by_type[$type_id] = [];
          foreach ($checked as $method_id) {
            if (in_array($method_id, $valid_method_ids, TRUE)) {
              $enabled_payment_methods_by_type[$type_id][$method_id] = $method_id;
            }
          }
        }
      }
      $this->configuration['payment_method'] = $enabled_payment_methods_by_type;

      $max = (int) ($values['max_installments'] ?? 12);
      $this->configuration['max_installments'] = max(1, min(12, $max));

      // The legacy setting was never connected to a provider fee calculation.
      $this->configuration['autofee'] = FALSE;
      $this->configuration['debug_logging'] = !empty($values['debug_logging']);
    }
  }

  /**
   * {@inheritdoc}
   *
   * O estorno exige pagamento aprovado e aprovação dos assinantes.
   */
  public function canRefundPayment(PaymentInterface $payment) {
    if (!parent::canRefundPayment($payment)) {
      return FALSE;
    }
    $event = new RefundEligibilityEvent($payment);
    $this->eventDispatcher->dispatch($event, RefundEligibilityEvent::NAME);
    return $event->isAllowed();
  }

  /**
   * Checks the ID returned for a provider resource against the requested ID.
   */
  private function remoteIdMatches(mixed $resource, string $expectedId): bool {
    $remoteId = is_object($resource)
      ? ($resource->id ?? NULL)
      : (is_array($resource) ? ($resource['id'] ?? NULL) : NULL);
    return is_scalar($remoteId) && (string) $remoteId === $expectedId;
  }

  /**
   * Finds the unique Commerce payment associated with a provider payment ID.
   *
   * A Mercado Pago payment must not be attached to multiple orders, nor may a
   * duplicate local row be selected arbitrarily during reconciliation.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   When the provider payment has conflicting Commerce associations.
   */
  private function existingCommercePayment(EntityStorageInterface $storage, OrderInterface $order, string $remoteId): ?PaymentInterface {
    $matches = $storage->loadByProperties([
      'payment_gateway' => $this->parentEntity->id(),
      'remote_id' => $remoteId,
    ]);
    if (count($matches) > 1) {
      throw new PaymentGatewayException('Payment has multiple conflicting Commerce records.', 0);
    }
    if ($matches === []) {
      return NULL;
    }
    $payment = reset($matches);
    if (!$payment instanceof PaymentInterface || (string) $payment->getOrderId() !== (string) $order->id()) {
      throw new PaymentGatewayException('Payment does not belong to this order.', 0);
    }
    return $payment;
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL): void {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    if (!$this->canRefundPayment($payment)) {
      throw new PaymentGatewayException('Refund is not allowed for this payment.');
    }
    $logger = $this->loggerFactory->get('Mercado Pago Refund');
    $remote_id = (string) $payment->getRemoteId();
    if ($remote_id === '' || !ctype_digit($remote_id) || (int) $remote_id < 1) {
      throw new PaymentGatewayException('The payment has no valid Mercado Pago reference.');
    }
    $lock_id = 'aculta_pagamentos_mercadopago.refund.' . $remote_id;
    if (!$this->lock->acquire($lock_id, 120)) {
      throw new PaymentGatewayException('Another refund operation is already in progress.');
    }

    try {
      // Reload under the shared lock so concurrent requests calculate against
      // the latest Commerce balance instead of a stale caller object.
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      $locked_payment = $payment->id() ? $payment_storage->load($payment->id()) : NULL;
      if (!$locked_payment instanceof PaymentInterface) {
        throw new PaymentGatewayException('The payment could not be reloaded for refund.');
      }
      $this->assertPaymentState($locked_payment, ['completed', 'partially_refunded']);
      if (!$this->canRefundPayment($locked_payment)) {
        throw new PaymentGatewayException('Refund is not allowed for this payment.');
      }

      // If not specified, refund the entire remaining balance.
      $refund_amount_price = $amount ?? $locked_payment->getBalance();
      if ($refund_amount_price === NULL || $refund_amount_price->isZero()) {
        throw new PaymentGatewayException('The remaining payment balance is missing.');
      }
      $old_refunded_amount = $locked_payment->getRefundedAmount();
      $payment_amount = $locked_payment->getAmount();
      if ($old_refunded_amount === NULL || $payment_amount === NULL) {
        throw new PaymentGatewayException('Payment refund amounts are missing.');
      }
      if ($refund_amount_price->getCurrencyCode() !== $payment_amount->getCurrencyCode()
        || $old_refunded_amount->getCurrencyCode() !== $payment_amount->getCurrencyCode()) {
        throw new PaymentGatewayException('The refund currency does not match the payment currency.');
      }
      try {
        $precision = MercadoPagoAmount::currencyPrecision($payment_amount->getCurrencyCode());
        $refund_number = MercadoPagoAmount::normalize($refund_amount_price->getNumber(), $precision);
        $payment_number = MercadoPagoAmount::normalize($payment_amount->getNumber(), $precision);
      }
      catch (MercadoPagoApiException) {
        throw new PaymentGatewayException('The refund amount cannot be represented in the payment currency.');
      }
      $this->assertRefundAmount($locked_payment, $refund_amount_price);
      $new_refunded_amount = $old_refunded_amount->add($refund_amount_price);
      $payment_id = (int) $remote_id;
      // The SDK accepts a float; all validation and endpoint selection happen
      // against exact currency precision before this boundary conversion.
      $refund_float = (float) $refund_number;
      $idempotency_key = 'aculta-refund-' . hash('sha256', implode('|', [
        $remote_id,
        $refund_amount_price->getCurrencyCode(),
        (string) $new_refunded_amount->getNumber(),
      ]));
      $request_options = new RequestOptions(NULL, NULL, ['x-idempotency-key' => $idempotency_key]);
      // Let Commerce own retries; a second network POST must re-enter with the
      // same durable local balance and therefore produce the same key.
      $request_options->setMaxRetries(0);
      $refund_client = $this->clientFactory->criar('reembolsos', $this->parentEntity);
      if (bccomp($refund_number, $payment_number, $precision) >= 0) {
        $refund_result = $refund_client->refundTotal($payment_id, $request_options);
      }
      else {
        $refund_result = $refund_client->refund($payment_id, $refund_float, $request_options);
      }
      $refund_id = is_object($refund_result)
        ? ($refund_result->id ?? NULL)
        : (is_array($refund_result) ? ($refund_result['id'] ?? NULL) : NULL);
      $refund_payment_id = is_object($refund_result)
        ? ($refund_result->payment_id ?? NULL)
        : (is_array($refund_result) ? ($refund_result['payment_id'] ?? NULL) : NULL);
      $refund_status = is_object($refund_result)
        ? ($refund_result->status ?? NULL)
        : (is_array($refund_result) ? ($refund_result['status'] ?? NULL) : NULL);
      if ($refund_status !== 'approved'
        || !is_scalar($refund_id)
        || filter_var((string) $refund_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE
        || !is_scalar($refund_payment_id)
        || (string) $refund_payment_id !== $remote_id) {
        throw new PaymentGatewayException('Mercado Pago has not confirmed the refund.');
      }

      if ($new_refunded_amount->lessThan($payment_amount)) {
        $locked_payment->setState('partially_refunded');
      }
      else {
        $locked_payment->setState('refunded');
      }
      $locked_payment->setRefundedAmount($new_refunded_amount);
      $locked_payment->setRemoteState($refund_status);
      $locked_payment->save();

      $order = $locked_payment->getOrder();
      $logger->info('Refund completed. Amount: @amount; order: @order_id; payment: @remote_id', [
        '@amount' => $refund_amount_price->getNumber(),
        '@order_id' => $order !== NULL ? $order->id() : '',
        '@remote_id' => $remote_id,
      ]);
    }
    catch (PaymentGatewayException $exception) {
      throw $exception;
    }
    catch (\Throwable) {
      // Provider responses can include private information. Log no response
      // bodies, access tokens, or SDK exception messages.
      $logger->error('Mercado Pago refund request failed for payment @remote_id.', ['@remote_id' => $remote_id]);
      throw new PaymentGatewayException('An error occurred while refunding the payment.');
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $logger = $this->loggerFactory->get('Mercado Pago Notifications');
    $response = $request->query->all();
    if (($response['topic'] ?? NULL) !== 'payment' || !isset($response['id']) || !is_scalar($response['id']) || !ctype_digit((string) $response['id']) || (int) $response['id'] < 1) {
      return new JsonResponse([], 400);
    }

    $payment_id = (string) $response['id'];
    $lock_id = 'aculta_pagamentos_mercadopago.payment.' . $payment_id;
    // Keep the provider snapshot and its Commerce write in one serialized
    // operation so a slower, older webhook cannot overwrite a newer status.
    if (!$this->lock->acquire($lock_id, 300)) {
      return new JsonResponse([], 503);
    }
    try {
      try {
        $payment_client = $this->clientFactory->criar('pagamentos', $this->parentEntity);
        $payment = $payment_client->get((int) $payment_id);
        if (!$this->remoteIdMatches($payment, $payment_id)) {
          $logger->warning('Payment lookup for @id returned a conflicting ID.', ['@id' => $payment_id]);
          return new JsonResponse([], 503);
        }
        $merchant_order = NULL;
        $merchant_order_id = NULL;
        if (isset($payment->order)) {
          $payment_order = is_object($payment->order)
            ? ($payment->order->id ?? NULL)
            : ($payment->order['id'] ?? NULL);
          if (!is_scalar($payment_order)
            || filter_var((string) $payment_order, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
            $logger->warning('Payment @id has an invalid merchant order reference.', ['@id' => $payment_id]);
            return new JsonResponse([], 503);
          }
          $merchant_order_id = (string) $payment_order;
          $merchant_order_client = $this->clientFactory->criar('pedidos_comerciante', $this->parentEntity);
          $merchant_order = $merchant_order_client->get((int) $merchant_order_id);
          if (!$this->remoteIdMatches($merchant_order, $merchant_order_id)) {
            $logger->warning('Merchant order lookup for @id returned a conflicting ID.', ['@id' => $merchant_order_id]);
            return new JsonResponse([], 503);
          }
        }
      }
      catch (\Throwable) {
        $logger->error('Mercado Pago webhook lookup failed for payment @id.', ['@id' => $payment_id]);
        return new JsonResponse([], 503);
      }

      $payment_reference = (string) ($payment->external_reference ?? '');
      $merchant_order_reference = (string) ($merchant_order->external_reference ?? '');
      if ($payment_reference !== '' && $merchant_order_reference !== '' && $payment_reference !== $merchant_order_reference) {
        $logger->warning('Payment @id has conflicting order references.', ['@id' => $payment_id]);
        return new JsonResponse();
      }
      $order_id = $merchant_order_reference !== '' ? $merchant_order_reference : $payment_reference;
      if ($order_id === '' || preg_match('/^[1-9][0-9]*$/', $order_id) !== 1) {
        return new JsonResponse();
      }
      $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);
      if (!$order instanceof OrderInterface) {
        return new JsonResponse();
      }

      $remote_status = (string) ($payment->status ?? '');
      $currency = (string) ($payment->currency_id ?? '');
      if ($currency === '') {
        $logger->warning('Payment @id has an invalid amount or currency for order @order_id.', [
          '@id' => $payment_id,
          '@order_id' => $order->id(),
        ]);
        return new JsonResponse();
      }
      try {
        $precision = MercadoPagoAmount::currencyPrecision($currency);
      }
      catch (MercadoPagoApiException) {
        $logger->warning('Payment @id has an invalid amount or currency for order @order_id.', [
          '@id' => $payment_id,
          '@order_id' => $order->id(),
        ]);
        return new JsonResponse();
      }
      try {
        $refunded_number = MercadoPagoAmount::normalize($payment->transaction_amount_refunded ?? 0, $precision);
      }
      catch (MercadoPagoApiException) {
        $logger->warning('Payment @id has an invalid refunded amount.', ['@id' => $payment_id]);
        return new JsonResponse();
      }
      $has_refund = bccomp($refunded_number, '0', $precision) > 0;
      $pending_status = in_array($remote_status, ['pending', 'in_process', 'authorized'], TRUE);
      $terminal_status = in_array($remote_status, ['cancelled', 'rejected'], TRUE);
      if (!in_array($remote_status, ['approved', 'refunded'], TRUE) && !$has_refund && !$pending_status && !$terminal_status) {
        $logger->info('Payment @id is not complete; remote status is @status.', [
          '@id' => $payment_id,
          '@status' => $remote_status !== '' ? $remote_status : 'unknown',
        ]);
        return new JsonResponse();
      }

      try {
        $amount_number = MercadoPagoAmount::normalize($payment->transaction_amount ?? NULL, $precision);
      }
      catch (MercadoPagoApiException) {
        $logger->warning('Payment @id has an invalid amount or currency for order @order_id.', [
          '@id' => $payment_id,
          '@order_id' => $order->id(),
        ]);
        return new JsonResponse();
      }
      $order_total = $order->getTotalPrice();
      if ($currency !== $order_total?->getCurrencyCode() || bccomp($amount_number, '0', $precision) <= 0) {
        $logger->warning('Payment @id has an invalid amount or currency for order @order_id.', [
          '@id' => $payment_id,
          '@order_id' => $order->id(),
        ]);
        return new JsonResponse();
      }
      $amount = new Price($amount_number, $currency);
      if (!$amount->equals($order_total)) {
        $logger->warning('Payment @id does not match order @order_id total.', [
          '@id' => $payment_id,
          '@order_id' => $order->id(),
        ]);
        return new JsonResponse();
      }

      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      try {
        $commerce_payment = $this->existingCommercePayment($payment_storage, $order, $payment_id);
      }
      catch (PaymentGatewayException) {
        $logger->warning('Payment @id has conflicting Commerce associations.', ['@id' => $payment_id]);
        return new JsonResponse([], 503);
      }
      $payment_created = FALSE;

      if ($commerce_payment instanceof PaymentInterface) {
      }
      elseif (!$terminal_status && (in_array($remote_status, ['approved', 'refunded'], TRUE) || $pending_status)) {
        $payment_method = NULL;
        if (!$order->get('payment_method')->isEmpty()) {
          $payment_method = $order->get('payment_method')->entity;
        }
        if (!$payment_method instanceof PaymentMethodInterface || $payment_method->getPaymentGatewayId() !== $this->parentEntity->id()) {
          $payment_method = $this->entityTypeManager->getStorage('commerce_payment_method')->createForCustomer(
            'aculta_mercadopago_checkout_pro',
            (string) $this->parentEntity->id(),
            $order->getCustomerId(),
            $order->getBillingProfile()
          );
        }
        $payment_method->setRemoteId($merchant_order_id ?? $payment_id);
        $payment_method->setReusable(FALSE);
        $payment_method->save();
        $order->set('payment_method', $payment_method);
        $order->save();

        $commerce_payment = $payment_storage->create([
          'state' => $pending_status ? 'pending' : 'authorization',
          'amount' => $amount,
          'payment_gateway' => $this->parentEntity->id(),
          'order_id' => $order->id(),
          'remote_id' => $payment_id,
          'remote_state' => $remote_status,
        ]);
        $payment_created = TRUE;
      }
      else {
        $logger->info('Terminal notification for payment @id has no matching Commerce payment.', ['@id' => $payment_id]);
        return new JsonResponse();
      }

      if ($terminal_status) {
        if (in_array($commerce_payment->getState()->getId(), ['authorization', 'pending'], TRUE)) {
          $commerce_payment->setRemoteState($remote_status);
          $commerce_payment->save();
          $commerce_payment->setState('authorization_voided');
          $commerce_payment->save();
        }
      }
      elseif ($remote_status === 'refunded' || $has_refund) {
        if (bccomp($refunded_number, '0', $precision) <= 0) {
          return new JsonResponse();
        }
        $refunded_amount = new Price($refunded_number, $currency);
        if ($refunded_amount->greaterThan($amount)) {
          $logger->warning('Refund for payment @id exceeds the captured amount.', ['@id' => $payment_id]);
          return new JsonResponse();
        }
        if ($payment_created) {
          $commerce_payment->save();
          $commerce_payment->setState('completed');
          $commerce_payment->save();
        }
        $commerce_payment->setState($refunded_amount->lessThan($amount) ? 'partially_refunded' : 'refunded');
        $commerce_payment->setRefundedAmount($refunded_amount);
        $commerce_payment->setRemoteState($remote_status);
        $commerce_payment->save();
      }
      elseif ($remote_status === 'approved'
          && in_array($commerce_payment->getState()->getId(), ['new', 'authorization', 'pending'], TRUE)) {
        $commerce_payment->setRemoteState($remote_status);
        $commerce_payment->save();
        $commerce_payment->setState('completed');
        $commerce_payment->save();
      }
      elseif ($pending_status) {
        if ($payment_created) {
          $commerce_payment->save();
        }
        else {
          $local_state = $commerce_payment->getState()->getId();
          // A provider lookup can lag behind a previous approved or terminal
          // event. Do not let that stale pending response regress its remote
          // state or overwrite a completed/refunded/voided Commerce record.
          if (in_array($local_state, ['new', 'authorization', 'pending'], TRUE)) {
            $changed = FALSE;
            if (in_array($local_state, ['new', 'authorization'], TRUE)) {
              $commerce_payment->setState('pending');
              $changed = TRUE;
            }
            if ($commerce_payment->getRemoteState() !== $remote_status) {
              $commerce_payment->setRemoteState($remote_status);
              $changed = TRUE;
            }
            if ($changed) {
              $commerce_payment->save();
            }
          }
        }
      }
      return new JsonResponse();
    }
    finally {
      $this->lock->release($lock_id);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request): void {
    $response = $request->query->all();
    $payment_id = $response['payment_id'] ?? NULL;
    if (!is_scalar($payment_id) || filter_var((string) $payment_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
      // Preserve the existing validation error for malformed payment IDs.
      $this->processReturn($order, $request);
      return;
    }

    $lock_id = 'aculta_pagamentos_mercadopago.payment.' . (string) $payment_id;
    if (!$this->lock->acquire($lock_id, 300)) {
      throw new PaymentGatewayException('Payment verification is already being processed.', 0);
    }
    try {
      $this->processReturn($order, $request);
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * Reconciles provider state while the caller holds the payment lock.
   */
  private function processReturn(OrderInterface $order, Request $request): void {
    $logger = $this->loggerFactory->get('Mercado Pago OnReturn');

    $order_checkout_pro_data = $order->getData('commerce_mercado_pago_checkout');
    if (empty($order_checkout_pro_data['preference_id'])) {
      throw new PaymentGatewayException('Preference id not found for this Checkout transaction.');
    }

    $response = $request->query->all();

    $payment_id = $response['payment_id'] ?? NULL;
    $merchant_order_id = $response['merchant_order_id'] ?? NULL;

    if (!is_scalar($payment_id) || filter_var((string) $payment_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE || !is_scalar($merchant_order_id) || filter_var((string) $merchant_order_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
      $logger->error('Missing or invalid required parameters on payment return.');
      throw new PaymentGatewayException('Invalid return from payment provider: missing required parameters.', 0);
    }

    // Confirm the payment remotely; do not trust the browser's status value.
    try {
      $payment_client = $this->clientFactory->criar('pagamentos', $this->parentEntity);
      $mp_payment = $payment_client->get((int) $payment_id);
    }
    catch (\Throwable $exception) {
      $logger->error('Mercado Pago API error verifying payment @id.', ['@id' => $payment_id]);
      throw new PaymentGatewayException('Could not verify payment with Mercado Pago.', 0);
    }
    if (!$this->remoteIdMatches($mp_payment, (string) $payment_id)) {
      $logger->error('Payment lookup for @id returned a conflicting ID.', ['@id' => (string) $payment_id]);
      throw new PaymentGatewayException('Could not verify payment with Mercado Pago.', 0);
    }

    $verified_status = (string) ($mp_payment->status ?? '');
    $pending_status = in_array($verified_status, ['pending', 'in_process', 'authorized'], TRUE);
    if ($verified_status !== 'approved' && !$pending_status) {
      $logger->error('Payment @id not approved (status: @status)', [
        '@id' => $payment_id,
        '@status' => $verified_status !== '' ? $verified_status : 'unknown',
      ]);
      throw new PaymentGatewayException('Payment was not approved.', 0);
    }

    // Registra o valor que o Mercado Pago confirmou, não o saldo do pedido: um
    // pagamento parcial ou de valor diferente não pode quitar o pedido.
    $verified_currency = (string) ($mp_payment->currency_id ?? '');
    try {
      $precision = MercadoPagoAmount::currencyPrecision($verified_currency);
      $verified_amount_number = MercadoPagoAmount::normalize($mp_payment->transaction_amount ?? NULL, $precision);
    }
    catch (MercadoPagoApiException) {
      $logger->error('Payment @id has an invalid amount or currency for order @order_id.', [
        '@id' => $payment_id,
        '@order_id' => $order->id(),
      ]);
      throw new PaymentGatewayException('Payment amount could not be verified.', 0);
    }
    $order_total = $order->getTotalPrice();
    if (bccomp($verified_amount_number, '0', $precision) <= 0 || $verified_currency !== $order_total?->getCurrencyCode()) {
      $logger->error('Payment @id amount @amount @currency does not match order @order_id currency', [
        '@id' => $payment_id,
        '@amount' => $verified_amount_number,
        '@currency' => $verified_currency !== '' ? $verified_currency : 'none',
        '@order_id' => $order->id(),
      ]);
      throw new PaymentGatewayException('Payment amount could not be verified.', 0);
    }
    $verified_amount = new Price($verified_amount_number, $verified_currency);
    if (!$verified_amount->equals($order_total)) {
      $logger->error('Payment @id amount does not match order @order_id total.', [
        '@id' => $payment_id,
        '@order_id' => $order->id(),
      ]);
      throw new PaymentGatewayException('Payment amount does not match this order.', 0);
    }

    // A non-empty payment reference must agree with the local order even when
    // its authenticated response also includes a Merchant Order ID.
    $payment_external_reference = $mp_payment->external_reference ?? NULL;
    if ($payment_external_reference !== NULL && $payment_external_reference !== ''
      && (!is_scalar($payment_external_reference) || (string) $payment_external_reference !== (string) $order->id())) {
      $logger->error('Payment @id external reference does not match order @order_id.', [
        '@id' => $payment_id,
        '@order_id' => $order->id(),
      ]);
      throw new PaymentGatewayException('Payment does not belong to this order.', 0);
    }

    // Confirm the merchant order's external_reference matches this order.
    $order_id_mp = NULL;
    if (isset($mp_payment->order)) {
      $order_id_mp = is_object($mp_payment->order) ? ($mp_payment->order->id ?? NULL) : ($mp_payment->order['id'] ?? NULL);
    }
    if ($order_id_mp === NULL && isset($mp_payment->order_id)) {
      $order_id_mp = is_object($mp_payment->order_id) ? ($mp_payment->order_id->id ?? NULL) : ($mp_payment->order_id ?? NULL);
    }
    if (!$order_id_mp) {
      // Without a merchant order in the payment, its external_reference must
      // prove the order association.
      $external_ref = $mp_payment->external_reference ?? NULL;
      if ((string) $external_ref !== (string) $order->id()) {
        $logger->error('Payment @id external_reference @ext does not match order @order_id', [
          '@id' => $payment_id,
          '@ext' => $external_ref ?? 'none',
          '@order_id' => $order->id(),
        ]);
        throw new PaymentGatewayException('Payment does not belong to this order.', 0);
      }
      $merchant_order_client = $this->clientFactory->criar('pedidos_comerciante', $this->parentEntity);
      try {
        $merchant_order = $merchant_order_client->get((int) $merchant_order_id);
      }
      catch (\Throwable) {
        $logger->error('Mercado Pago API error fetching returned merchant order.');
        throw new PaymentGatewayException('Could not verify payment order.', 0);
      }
      if (!$this->remoteIdMatches($merchant_order, (string) $merchant_order_id)) {
        $logger->error('Merchant order lookup for @id returned a conflicting ID.', ['@id' => (string) $merchant_order_id]);
        throw new PaymentGatewayException('Could not verify payment order.', 0);
      }
      $merchant_reference = (string) ($merchant_order->external_reference ?? '');
      $merchant_payments = $merchant_order->payments ?? [];
      $payment_is_linked = FALSE;
      foreach ($merchant_payments as $merchant_payment) {
        $merchant_payment = is_array($merchant_payment) ? $merchant_payment : (array) $merchant_payment;
        if ((string) ($merchant_payment['id'] ?? '') === (string) $payment_id) {
          $payment_is_linked = TRUE;
          break;
        }
      }
      if ($merchant_reference !== (string) $order->id() || !$payment_is_linked) {
        $logger->error('Payment @id is not linked to returned merchant order @merchant_order_id.', [
          '@id' => $payment_id,
          '@merchant_order_id' => $merchant_order_id,
        ]);
        throw new PaymentGatewayException('Payment does not belong to this order.', 0);
      }
    }
    else {
      if ((string) $order_id_mp !== (string) $merchant_order_id) {
        $logger->error('Payment @id merchant order @actual does not match returned merchant order @returned.', [
          '@id' => $payment_id,
          '@actual' => $order_id_mp,
          '@returned' => $merchant_order_id,
        ]);
        throw new PaymentGatewayException('Payment does not belong to this order.', 0);
      }
      $merchant_order_client = $this->clientFactory->criar('pedidos_comerciante', $this->parentEntity);
      try {
        $merchant_order = $merchant_order_client->get((int) $order_id_mp);
      }
      catch (\Throwable) {
        $logger->error('Mercado Pago API error fetching merchant order @id.', ['@id' => $order_id_mp]);
        throw new PaymentGatewayException('Could not verify payment order.', 0);
      }
      if (!$this->remoteIdMatches($merchant_order, (string) $order_id_mp)) {
        $logger->error('Merchant order lookup for @id returned a conflicting ID.', ['@id' => (string) $order_id_mp]);
        throw new PaymentGatewayException('Could not verify payment order.', 0);
      }
      $external_ref = $merchant_order->external_reference ?? NULL;
      if ((string) $external_ref !== (string) $order->id()) {
        $logger->error('Payment @id merchant order @mo_id external_reference @ext does not match order @order_id', [
          '@id' => $payment_id,
          '@mo_id' => $order_id_mp,
          '@ext' => $external_ref,
          '@order_id' => $order->id(),
        ]);
        throw new PaymentGatewayException('Payment does not belong to this order.', 0);
      }
    }

    /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    // If payment was already saved from a signed webhook (OnNotify)
    // no need to save the payment again.
    try {
      $existing_payment = $this->existingCommercePayment($payment_storage, $order, $payment_id);
    }
    catch (PaymentGatewayException $exception) {
      $logger->error('Payment @id has conflicting Commerce associations.', ['@id' => $payment_id]);
      throw $exception;
    }

    if ($existing_payment instanceof PaymentInterface) {
      $existing_state = $existing_payment->getState()->getId();
      if ($verified_status === 'approved' && in_array($existing_state, ['new', 'authorization', 'pending'], TRUE)) {
        $existing_payment->setRemoteState($verified_status);
        $existing_payment->save();
        $existing_payment->setState('completed');
        $existing_payment->save();
      }
      elseif ($pending_status && in_array($existing_state, ['new', 'authorization', 'pending'], TRUE)) {
        $changed = FALSE;
        if (in_array($existing_state, ['new', 'authorization'], TRUE)) {
          $existing_payment->setState('pending');
          $changed = TRUE;
        }
        if ($existing_payment->getRemoteState() !== $verified_status) {
          $existing_payment->setRemoteState($verified_status);
          $changed = TRUE;
        }
        if ($changed) {
          $existing_payment->save();
        }
      }
      $logger->info('Payment already saved from a signed webhook or return.');
      return;
    }
    // If OnNotify method failed for some reason
    // we continue to save the payment information.
    // Mescla os dados para preservar preference_id no retorno.
    $checkout_data = $order->getData('commerce_mercado_pago_checkout') ?? [];
    $checkout_data['remote_id'] = $merchant_order_id;
    $order->setData('commerce_mercado_pago_checkout', $checkout_data);

    $payment_method = NULL;
    // Reuse o método de pagamento atual, quando disponível.
    if (!$order->get('payment_method')->isEmpty()) {
      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $payment_method = $order->get('payment_method')->entity;
    }
    // If the order doesn't reference a payment method yet, or if the payment
    // method doesn't reference the right gateway, create a new one.
    if (!$payment_method || $payment_method->getPaymentGatewayId() !== $this->parentEntity->id()) {
      // Create a payment method.
      $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
      assert($payment_method_storage instanceof PaymentMethodStorageInterface);
      $payment_method = $payment_method_storage->createForCustomer(
        'aculta_mercadopago_checkout_pro',
        (string) $this->parentEntity->id(),
        $order->getCustomerId(),
        $order->getBillingProfile()
      );
    }

    $payment_method->setRemoteId((string) $merchant_order_id);
    $payment_method->setReusable(FALSE);
    $payment_method->save();

    $order->set('payment_method', $payment_method);
    $order->save();

    $logger->info('Payment information saved successfully.<br>Payment Type: @type<br>Merchant order id: @mo_id<br>Preference id: @pref_id', [
      '@type' => $response['payment_type'] ?? 'n/a',
      '@mo_id' => $merchant_order_id,
      '@pref_id' => $response['preference_id'] ?? 'n/a',
    ]);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $payment_storage->create([
      'state' => $pending_status ? 'pending' : 'authorization',
      'amount' => $verified_amount,
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'remote_id' => $payment_id,
      'remote_state' => $verified_status,
    ]);
    $payment->save();

    if ($pending_status) {
      $this->messenger()->addWarning('Payment is pending confirmation. We will update your order when Mercado Pago confirms it.');
    }
    else {
      $payment->setState('completed');
      $payment->save();
      $this->messenger()->addStatus('Payment has been successfully processed.');
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method to create, which is unsupported for offsite checkout.
   * @param array<string, mixed> $payment_details
   *   Additional payment details, which are unsupported for offsite checkout.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   Always; Checkout Pro creates payment methods in its offsite flow.
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details): PaymentMethodInterface {
    // Checkout Pro creates payment methods during onReturn/onNotify.
    throw new PaymentGatewayException('Creating a payment method outside the checkout flow is not supported.', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method): void {
    $payment_method->delete();
  }

  /**
   * Builds a customer profile, assigned to the order's owner.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The customer profile.
   */
  protected function buildCustomerProfile(OrderInterface $order): ProfileInterface {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $this->entityTypeManager->getStorage('profile')->create([
      'uid' => $order->getCustomerId(),
      'type' => 'customer',
    ]);
    return $profile;
  }

  /**
   * Mercado Pago supported countries (store/site country for payment options).
   *
   * @var string[]
   */
  public static $supportedCountries = [
    'AR' => 'Argentina',
    'BR' => 'Brasil',
    'CL' => 'Chile',
    'CO' => 'Colombia',
    'MX' => 'México',
    'PE' => 'Perú',
    'UY' => 'Uruguay',
  ];

  /**
   * Builds excluded_payment_methods for a Checkout Preference.
   *
   * Sends methods that were NOT selected so Mercado Pago excludes them. Account
   * money and wallet (digital_currency) are never excluded (always enabled).
   *
   * Exclusions are global in the Checkout Pro API. A method disabled for any
   * type is hidden for all types.
   *
   * @param array<string, mixed> $configuration
   *   The gateway configuration.
   *
   * @return list<array{id: string}>
   *   List of ['id' => 'method_id'] for the preference API.
   */
  public static function getExcludedPaymentMethodsForPreference(array $configuration): array {
    $country = $configuration['country'] ?? '';
    $payment_types = $configuration['payment_types'] ?? [];
    $payment_method = $configuration['payment_method'] ?? [];

    if (!$country || empty($payment_types)) {
      return [];
    }

    $selected_type_ids = array_map('strval', array_keys($payment_types));
    $by_type = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, $selected_type_ids);

    $excluded = [];
    $is_per_type = !empty($payment_method) && is_array(reset($payment_method));

    if ($is_per_type) {
      // Exclusions apply globally, so one disabled type excludes the method.
      foreach ($by_type as $type_id => $methods_for_type) {
        $available_in_type = array_keys($methods_for_type);
        $enabled_in_type = array_keys($payment_method[$type_id] ?? []);
        $excluded_for_type = array_diff($available_in_type, $enabled_in_type);
        $excluded = array_merge($excluded, $excluded_for_type);
      }
      $excluded = array_unique($excluded);
    }
    else {
      // Legacy flat config: exclude methods not in the flat enabled list.
      $all_method_ids = [];
      foreach ($by_type as $methods) {
        foreach (array_keys($methods) as $method_id) {
          $all_method_ids[$method_id] = TRUE;
        }
      }
      $all_method_ids = array_keys($all_method_ids);
      $selected_method_ids = array_keys($payment_method);
      $excluded = array_diff($all_method_ids, $selected_method_ids);
    }

    // Account money and digital currency are always enabled.
    $excluded = array_diff($excluded, MercadoPagoPaymentMethodsMap::$alwaysEnabledPaymentTypes);

    return array_map(function ($id) {
      return ['id' => $id];
    }, array_values($excluded));
  }

  /**
   * Builds excluded_payment_types for a Checkout Preference.
   *
   * Sends types that were NOT selected so Mercado Pago excludes them. Account
   * money and digital_currency (wallet) are never excluded (always enabled).
   *
   * @param array<string, mixed> $configuration
   *   The payment gateway configuration (country, payment_types).
   *
   * @return list<array{id: string}>
   *   List of ['id' => 'type_id'] for the preference API.
   */
  public static function getExcludedPaymentTypesForPreference(array $configuration): array {
    $country = $configuration['country'] ?? '';
    $payment_types = $configuration['payment_types'] ?? [];
    if (!$country) {
      return [];
    }
    // Exclude only country-supported types; wallets are always enabled.
    $configurable_for_country = MercadoPagoPaymentMethodsMap::getConfigurablePaymentTypesForCountry($country);
    $configurable_type_ids = array_keys($configurable_for_country);
    $selected_type_ids = array_keys($payment_types);
    $excluded = array_diff($configurable_type_ids, $selected_type_ids);

    return array_map(function ($id) {
      return ['id' => $id];
    }, array_values($excluded));
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request): void {
    $this->messenger()->addMessage($this->t('You have canceled checkout at @gateway but may resume the checkout process here when you are ready.', [
      '@gateway' => $this->getDisplayLabel(),
    ]));
  }

}
