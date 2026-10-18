<?php

namespace Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodStorageInterface;
use Drupal\commerce_payment\PaymentStorageInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\aculta_pagamentos_mercadopago\Event\RefundEligibilityEvent;
use Drupal\commerce_price\Price;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Element\Checkboxes;
use GuzzleHttp\Exception\BadResponseException;
use MercadoPago\Client\MerchantOrder\MerchantOrderClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Payment\PaymentRefundClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the Mercado Pago Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "aculta_mercadopago_checkout_pro",
 *   label = @Translation("Mercado Pago CheckoutPro"),
 *   display_label = @Translation("Mercado Pago"),
 *   modes = {
 *     "test" = @Translation("Sandbox - Test account"),
 *     "stage" = @Translation("Stage - Production account & Test credentials"),
 *     "live" = @Translation("Live - Production account & Production credentials"),
 *   },
 *   forms = {
 *     "offsite-payment" = "Drupal\aculta_pagamentos_mercadopago\PluginForm\Checkout\RedirectCheckoutForm",
 *   },
 *   payment_method_types = {"aculta_mercadopago_checkout_pro"},
 *   credit_card_types = {
 *      "amex", "mastercard", "visa",
 *   },
 * )
 */
class CheckoutPro extends OffsitePaymentGatewayBase implements SupportsRefundsInterface {

  /**
   * Mercado Pago Integrator ID for integration attribution.
   */
  public const INTEGRATOR_ID = 'dev_41f92bb906be11f1865d6a6135317fac';

  /**
   * Payment type IDs that are shown as one "Card brands" block (no duplicate methods).
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
   * {@inheritdoc}
   *
   * Constructor receives only configuration, plugin_id, and plugin_definition.
   * Other dependencies are assigned in PaymentGatewayBase::create().
   *
   * @param array<string, mixed> $configuration
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
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->requestStack = $container->get('request_stack');
    $instance->loggerFactory = $container->get('logger.factory');
    $instance->eventDispatcher = $container->get('event_dispatcher');
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
   * Whether extra debug logging is enabled for this gateway.
   *
   * When true, "Mercado Pago debug" logs (request payloads, IPN data, etc.)
   * are written to the log.
   *
   * @return bool
   */
  protected function isDebugLoggingEnabled(): bool {
    return !empty($this->configuration['debug_logging']);
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
      'country' => '',
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
   * @return array<string, mixed>
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $mode_name = 'configuration[' . $this->pluginId . '][mode]';
    $form['#attached']['library'][] = 'aculta_pagamentos_mercadopago/gateway_admin';

    $form['credentials_test'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sandbox - Test account'),
      '#description' => $this->t('Use your Mercado Pago test account credentials (Public key and Access token from the test environment). Use them to simulate payments and test your integrations from a trial account. For more information visit: https://www.mercadopago.com/developers/en/docs/your-integrations/test/accounts'),
      '#attributes' => ['class' => ['mercado-pago-credentials', 'mercado-pago-credentials-test']],
      '#states' => [
        'visible' => [
          ':input[name="' . $mode_name . '"]' => ['value' => 'test'],
        ],
      ],
    ];
    $form['credentials_test']['public_key_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public key'),
      '#description' => $this->t('Public key for Mercado Pago.'),
      '#default_value' => $this->configuration['public_key_test'],
    ];
    $form['credentials_test']['access_token_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token'),
      '#description' => $this->t('Access token for Mercado Pago.'),
      '#default_value' => $this->configuration['access_token_test'],
    ];

    $form['credentials_stage'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Stage - Production account & Test credentials'),
      '#description' => $this->t('Use test credentials (Public key and Access token) against your production account to try the flow without real charges. For more information visit: https://www.mercadopago.com/developers/en/docs/your-integrations/credentials'),
      '#attributes' => ['class' => ['mercado-pago-credentials', 'mercado-pago-credentials-stage']],
      '#states' => [
        'visible' => [
          ':input[name="' . $mode_name . '"]' => ['value' => 'stage'],
        ],
      ],
    ];
    $form['credentials_stage']['public_key_stage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public key'),
      '#description' => $this->t('Public key for Mercado Pago.'),
      '#default_value' => $this->configuration['public_key_stage'],
    ];
    $form['credentials_stage']['access_token_stage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token'),
      '#description' => $this->t('Access token for Mercado Pago.'),
      '#default_value' => $this->configuration['access_token_stage'],
    ];

    $form['credentials_prod'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Live - Production account & Production credentials'),
      '#description' => $this->t('Use your production credentials (Public key and Access token) for real payments. For more information visit: https://www.mercadopago.com/developers/en/docs/your-integrations/credentials'),
      '#attributes' => ['class' => ['mercado-pago-credentials', 'mercado-pago-credentials-prod']],
      '#states' => [
        'visible' => [
          ':input[name="' . $mode_name . '"]' => ['value' => 'live'],
        ],
      ],
    ];
    $form['credentials_prod']['public_key_prod'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public key'),
      '#description' => $this->t('Public key for Mercado Pago.'),
      '#default_value' => $this->configuration['public_key_prod'],
    ];
    $form['credentials_prod']['access_token_prod'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token'),
      '#description' => $this->t('Access token for Mercado Pago.'),
      '#default_value' => $this->configuration['access_token_prod'],
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client id'),
      '#description' => $this->t('The Client id for Mercado Pago.'),
      '#default_value' => $this->configuration['client_id'],
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('The Client Secret for Mercado Pago.'),
      '#default_value' => $this->configuration['client_secret'],
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
      '#type' => 'select',
      '#title' => $this->t('Store country'),
      '#description' => $this->t('Country where the store operates. Payment types and methods are filtered by this.'),
      '#default_value' => $this->configuration['country'],
      '#options' => ['' => $this->t('- Select -')] + array_map([$this, 't'], self::$supportedCountries),
      '#empty_value' => '',
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [static::class, 'ajaxRefreshPaymentOptions'],
        'wrapper' => $wrapper_id,
        'event' => 'change',
      ],
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

    $form['autofee'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autofee'),
      '#description' => $this->t('If set, the fee charged by the acquirer will be calculated and added to the transaction amount. @TODO'),
      '#default_value' => $this->configuration['autofee'],
    ];

    $form['debug_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable extra debug logging'),
      '#description' => $this->t('When enabled, detailed Mercado Pago debug messages (e.g. request payloads, IPN data) are written to the log. Useful for integration troubleshooting; leave disabled in production to reduce log volume.'),
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
    // Resolve config key the same way as the AJAX callback: values are stored under
    // plugin_id (e.g. aculta_mercadopago_checkout_pro), not the form tree key 'form'.
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
    // Only show methods for configurable types; always-enabled types are not in the form.
    $selected_type_ids = array_diff($selected_type_ids, MercadoPagoPaymentMethodsMap::$alwaysEnabledPaymentTypes);
    // When country changed, only keep types that exist for the new country.
    if ($country && isset(MercadoPagoPaymentMethodsMap::$methodsByCountryAndType[$country])) {
      $valid_types = array_keys(MercadoPagoPaymentMethodsMap::getConfigurablePaymentTypesForCountry($country));
      $selected_type_ids = array_intersect($selected_type_ids, $valid_types);
    }

    // Card types (Credit, Debit, Prepaid) are shown as one "Card brands" block; other types stay per-type.
    $selected_card_types = array_intersect(static::CARD_PAYMENT_TYPE_IDS, $selected_type_ids);
    $other_type_ids = array_diff($selected_type_ids, static::CARD_PAYMENT_TYPE_IDS);

    $saved_payment_method = $this->configuration['payment_method'] ?? [];
    $saved_methods_per_type = (bool) (is_array($saved_payment_method) && $saved_payment_method !== [] && is_array(reset($saved_payment_method)));

    $selected_method_ids = [];
    if (!empty($wrapper_values['payment_method']) && is_array($wrapper_values['payment_method'])) {
      foreach ($wrapper_values['payment_method'] as $key => $type_data) {
        if (!is_array($type_data)) {
          continue;
        }
        $methods_value = $type_data['methods'] ?? $type_data;
        foreach (Checkboxes::getCheckedCheckboxes(is_array($methods_value) ? $methods_value : []) as $method_id) {
          $selected_method_ids[] = $method_id;
        }
      }
    }

    // Only show type options when a country is selected; when "None", show nothing.
    $type_options = [];
    if ($country && isset(MercadoPagoPaymentMethodsMap::$methodsByCountryAndType[$country])) {
      $valid_types = array_keys(MercadoPagoPaymentMethodsMap::getConfigurablePaymentTypesForCountry($country));
      foreach (MercadoPagoPaymentMethodsMap::getConfigurablePaymentTypesForCountry($country) as $type_id => $label) {
        $type_options[$type_id] = $this->t($label);
      }
      $normalized_types = array_combine($selected_type_ids, $selected_type_ids) ?: [];
      $form_state->setValue(array_merge($form_parents, ['payment_options_wrapper', 'payment_types']), $normalized_types);
      $by_type = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, $selected_type_ids);
      $normalized_methods = [];

      // Card brands block: one set of checkboxes when any of Credit, Debit or Prepaid is selected.
      // Use union of all card methods for the country so submitted values stay in #options when
      // the user toggles Credit/Debit/Prepaid (avoids "value X is not allowed" on AJAX).
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
          // When saved config is flat (method_id => method_id), use keys as checked.
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

      // One "Card brands" block when any of Credit, Debit or Prepaid is selected.
      // Show all card brands for the country (union of Credit + Debit + Prepaid) so options
      // stay the same when the user toggles types and no "value not allowed" on AJAX submit.
      if (!empty($selected_card_types)) {
        $by_type_all_cards = MercadoPagoPaymentMethodsMap::getMethodsForCountryAndTypes($country, static::CARD_PAYMENT_TYPE_IDS);
        $card_options = [];
        foreach ($by_type_all_cards as $methods_for_type) {
          foreach ($methods_for_type as $method_id => $label) {
            if (!isset($card_options[$method_id])) {
              $card_options[$method_id] = $this->t($label);
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
          $options[$method_id] = $this->t($label);
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
   *   The payment_options_wrapper form element (with #prefix/#suffix for the id).
   */
  public static function ajaxRefreshPaymentOptions(array $form, FormStateInterface $form_state): array {
    $wrapper_id = 'mercado-pago-payment-options-wrapper';

    // Form state stores config under plugin_id (e.g. aculta_mercadopago_checkout_pro), not "form".
    $values_config = $form_state->getValues()['configuration'] ?? [];
    $config_key = array_keys($values_config)[0] ?? array_keys(\Drupal::request()->request->all()['configuration'] ?? [])[0] ?? 'form';
    $country = (string) ($form_state->getValue(['configuration', $config_key, 'country']) ?? '');
    if ($country === '') {
      $input = $form_state->getUserInput()['configuration'][$config_key]['country'] ?? NULL;
      $country = (string) ($input ?? \Drupal::request()->request->all()['configuration'][$config_key]['country'] ?? '');
    }

    // Find the wrapper in the form tree (plugin form is under key 'form' in configuration).
    // Skip non-array entries (e.g. #inline_form is the PluginConfiguration object).
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
   *   The form array (modified by reference). Callers pass generic array from Form API.
   * @phpstan-ignore-next-line missingType.iterableValue (Form API passes untyped array)
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Remove card brand method IDs that are no longer valid for the selected payment
    // types (e.g. user unchecked Credit but "oca" was selected). Prevents "The submitted
    // value X in the configuration element is not allowed" when options change via AJAX.
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

    $country = (string) ($values['country'] ?? '');
    if ($country === '') {
      $form_state->setError($form['country'], $this->t('You must select a store country to save the Mercado Pago payment option.'));
    }

    $max_installments = (int) ($values['max_installments'] ?? 0);
    if ($max_installments < 1 || $max_installments > 12) {
      $form_state->setError($form['max_installments'], $this->t('Maximum installments must be between 1 and 12.'));
    }

    $creds_test = $values['credentials_test'] ?? [];
    $creds_stage = $values['credentials_stage'] ?? [];
    $creds_prod = $values['credentials_prod'] ?? [];
    if ($values['mode'] === 'test') {
      if (empty($creds_test['access_token_test'])) {
        $form_state->setError($form['credentials_test']['access_token_test'], $this->t('Access token cannot be empty.'));
      }
      if (empty($creds_test['public_key_test'])) {
        $form_state->setError($form['credentials_test']['public_key_test'], $this->t('Public key cannot be empty.'));
      }
    }
    elseif ($values['mode'] === 'stage') {
      if (empty($creds_stage['access_token_stage'])) {
        $form_state->setError($form['credentials_stage']['access_token_stage'], $this->t('Access token cannot be empty.'));
      }
      if (empty($creds_stage['public_key_stage'])) {
        $form_state->setError($form['credentials_stage']['public_key_stage'], $this->t('Public key cannot be empty.'));
      }
    }
    else {
      if (empty($creds_prod['access_token_prod'])) {
        $form_state->setError($form['credentials_prod']['access_token_prod'], $this->t('Access token cannot be empty.'));
      }
      if (empty($creds_prod['public_key_prod'])) {
        $form_state->setError($form['credentials_prod']['public_key_prod'], $this->t('Public key cannot be empty.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array &$form
   *   The form array (modified by reference). Callers pass generic array from Form API.
   * @phpstan-ignore-next-line missingType.iterableValue (Form API passes untyped array)
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $creds_test = $values['credentials_test'] ?? [];
      $creds_stage = $values['credentials_stage'] ?? [];
      $creds_prod = $values['credentials_prod'] ?? [];
      $this->configuration['public_key_test'] = $creds_test['public_key_test'] ?? '';
      $this->configuration['access_token_test'] = $creds_test['access_token_test'] ?? '';
      $this->configuration['public_key_stage'] = $creds_stage['public_key_stage'] ?? '';
      $this->configuration['access_token_stage'] = $creds_stage['access_token_stage'] ?? '';
      $this->configuration['public_key_prod'] = $creds_prod['public_key_prod'] ?? '';
      $this->configuration['access_token_prod'] = $creds_prod['access_token_prod'] ?? '';
      $this->configuration['client_id'] = $values['client_id'];
      $this->configuration['client_secret'] = $values['client_secret'];
      $this->configuration['redirect_mode'] = $values['redirect_mode'];
      $country = $values['country'] ?? '';
      $this->configuration['country'] = $country;

      // Unchecked checkboxes are not sent by the browser, and nested form state
      // can merge with defaults and show all as checked. Use raw request so we
      // only get actually submitted (checked) options. Try both possible keys
      // under 'configuration' (plugin_id from #parents, or 'form' from inline form).
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
      // Only save types that are valid for the selected country (avoids storing e.g. "ticket" when country has no ticket).
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

      // Save payment_method per type_id. Card types (Credit/Debit/Prepaid) share one form block (card_brands).
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
          // Save the same card brands list for each selected type (Credit/Debit/Prepaid).
          // Selection is independent of type: user can select e.g. Creditel even if only
          // Prepaid is enabled; we store all checked brands per type for form reload.
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

      $this->configuration['autofee'] = $values['autofee'];
      $this->configuration['debug_logging'] = !empty($values['debug_logging']);
    }
  }

  /**
   * {@inheritdoc}
   *
   * O estorno só é oferecido quando o pagamento está aprovado e nenhum assinante negou a operação.
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
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL): void {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    if (!$this->canRefundPayment($payment)) {
      throw new PaymentGatewayException('Refund is not allowed for this payment.');
    }
    $logger = $this->loggerFactory->get('Mercado Pago Refund');

    // If not specified, refund the entire amount.
    $refund_amount_price = $amount ?? $payment->getAmount();
    if ($refund_amount_price === NULL) {
      throw new PaymentGatewayException('Payment amount is missing.');
    }
    $this->assertRefundAmount($payment, $refund_amount_price);
    $old_refunded_amount = $payment->getRefundedAmount();
    if ($old_refunded_amount === NULL) {
      throw new PaymentGatewayException('Refunded amount is missing.');
    }
    $new_refunded_amount = $old_refunded_amount->add($refund_amount_price);

    $payment_amount = $payment->getAmount();
    if ($payment_amount === NULL) {
      throw new PaymentGatewayException('Payment amount is missing.');
    }

    try {
      MercadoPagoConfig::setAccessToken($this->getAccessToken());
      MercadoPagoConfig::setIntegratorId(self::INTEGRATOR_ID);
      $refundClient = new PaymentRefundClient();
      $payment_id = (int) $payment->getRemoteId();
      $refund_float = (float) $refund_amount_price->getNumber();
      if ($refund_float >= (float) $payment_amount->getNumber()) {
        $refund_result = $refundClient->refundTotal($payment_id);
      }
      else {
        $refund_result = $refundClient->refund($payment_id, $refund_float);
      }
    }
    catch (BadResponseException $exception) {
      $logger->error($exception->getResponse()->getBody()->getContents());
      throw new PaymentGatewayException('An error occurred while refunding the payment.');
    }
    catch (\MercadoPago\Exceptions\MPApiException $e) {
      if ($this->isDebugLoggingEnabled()) {
        $this->loggerFactory->get('Mercado Pago debug')->debug('Refund error: <pre>@data</pre>', [
          '@data' => $e->getApiResponse()->getContent(),
        ]);
      }
      throw new PaymentGatewayException('Refund error: ' . $e->getMessage());
    }

    if ($new_refunded_amount->lessThan($payment_amount)) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }
    $payment->setRefundedAmount($new_refunded_amount);
    $payment->setRemoteState($refund_result->status ?? 'refunded');
    $payment->save();

    $order = $payment->getOrder();
    $logger->info('Refund completed.<br>Refunded amount: @amount<br>Order id: @order_id<br>Payment: @remote_id', [
      '@amount' => $refund_amount_price->getNumber(),
      '@order_id' => $order !== NULL ? $order->id() : '',
      '@remote_id' => $payment->getRemoteId(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $logger = $this->loggerFactory->get('Mercado Pago Notifications');

    /*
    $logger->debug('Headers: <pre>@data</pre>', [
    '@data' => print_r($request->headers, TRUE),
    ]);
     */

    // Reacts to IPN events.
    $response = $request->query->all();
    // $logger->info('Response: ' . print_r($response, TRUE));
    MercadoPagoConfig::setAccessToken($this->getAccessToken());
    MercadoPagoConfig::setIntegratorId(self::INTEGRATOR_ID);

    $paymentClient = new PaymentClient();
    $merchantOrderClient = new MerchantOrderClient();

    if (isset($response["topic"])) {
      // https://www.mercadopago.com.uy/developers/es/docs/checkout-pro/additional-content/your-integrations/notifications/ipn
      $merchant_order = NULL;
      $ipn_payment = NULL;
      try {
        switch ($response["topic"]) {
          case "payment":
            // Get the payment and the corresponding merchant_order reported by the IPN.
            $ipn_payment = $paymentClient->get((int) $response["id"]);
            $order_id_mp = NULL;
            if (isset($ipn_payment->order)) {
              $order_id_mp = is_object($ipn_payment->order) ? ($ipn_payment->order->id ?? NULL) : ($ipn_payment->order['id'] ?? NULL);
            }
            if ($order_id_mp) {
              $merchant_order = $merchantOrderClient->get((int) $order_id_mp);
            }
            break;

          case "merchant_order":
            $merchant_order = $merchantOrderClient->get((int) $response["id"]);
            break;
        }
      }
      catch (MPApiException $e) {
        $logger->error('IPN API error: @message', ['@message' => $e->getMessage()]);
        return new JsonResponse();
      }

      if (!$merchant_order) {
        return new JsonResponse();
      }

      $order_id = $merchant_order->external_reference ?? NULL;
      if ($order_id === NULL || $order_id === '') {
        return new JsonResponse();
      }
      $order = $this->entityTypeManager->getStorage('commerce_order')->load((string) $order_id);
      if (!$order) {
        return new JsonResponse();
      }

      if ($response["topic"] == "merchant_order") {

        if ($this->isDebugLoggingEnabled()) {
          $this->loggerFactory->get('Mercado Pago debug')->debug('Merchant order data: <pre>@data</pre>', [
            '@data' => print_r($merchant_order, TRUE),
          ]);
        }

        $paid_amount = 0;
        $payments = $merchant_order->payments ?? [];
        foreach ($payments as $mo_payment) {
          $mo_data = is_array($mo_payment) ? $mo_payment : (array) $mo_payment;
          $status = $mo_data['status'] ?? NULL;
          $total_paid = $mo_data['total_paid_amount'] ?? 0;
          if ($status == 'approved') {
            $paid_amount += (float) $total_paid;
          }
        }

        // If the payment's transaction amount is equal (or bigger)
        // than the merchant_order's amount you can release your items.
        if ($paid_amount >= $merchant_order->total_amount) {
          if (!is_null($merchant_order->shipments) && $merchant_order->shipping_cost > 0) {
            // If the merchant_order has shipments
            // if($merchant_order->shipments[0]->status == "ready_to_ship") {.
            $logger->info("Totally paid. Print the label and release your item.<br>Order ID: " . $order_id . "<br>Paid amount: " . $paid_amount);
            // }
          }
          else {
            // The merchant_order doesn't have any shipments.
            $logger->info("Totally paid. Release your item.<br>Order ID: " . $order_id . "<br>Paid amount: " . $paid_amount);
          }
        }
        else {
          $logger->info("Order received at Mercado Pago.<br>Order ID: " . $order_id . "<br>Paid amount: " . $paid_amount);
        }

      }
      else {

        // $response["topic"] == "payment"
        if (isset($ipn_payment)) {

          if ($this->isDebugLoggingEnabled()) {
            $this->loggerFactory->get('Mercado Pago debug')->debug('Payment data: <pre>@data</pre>', [
              '@data' => print_r($ipn_payment, TRUE),
            ]);
          }

          $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

          $payment_ids = $payment_storage->getQuery()
            ->condition('order_id', $order_id)
            ->condition('remote_id', $ipn_payment->id)
            ->accessCheck(FALSE)
            ->sort('payment_id', 'DESC')
            ->range(0, 1)
            ->execute();

          // If payment was already saved we don't need to save it again.
          if (!$payment_ids) {

            $payment_method = NULL;

            if ($order instanceof OrderInterface && !$order->get('payment_method')->isEmpty()) {
              /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
              $payment_method = $order->get('payment_method')->entity;
            }
            // If the order doesn't reference a payment method,
            // or if the payment method doesn't reference
            // the right gateway, create a new one.
            if (!$payment_method || $payment_method->getPaymentGatewayId() !== $this->parentEntity->id()) {
              if (!($order instanceof OrderInterface)) {
                throw new PaymentGatewayException('Order is required to create a payment method.');
              }
              /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
              $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
              $payment_method = $payment_method_storage->createForCustomer(
                'aculta_mercadopago_checkout_pro',
                (string) $this->parentEntity->id(),
                $order->getCustomerId(),
                $order->getBillingProfile()
              );
            }

            $payment_method->setRemoteId((string) $merchant_order->id);
            $payment_method->setReusable(FALSE);
            $payment_method->save();

            if ($order instanceof OrderInterface) {
              $order->set('payment_method', $payment_method);
              $order->save();
            }

            $txn_details = $ipn_payment->transaction_details ?? NULL;
            $total_paid = is_array($txn_details) ? ($txn_details['total_paid_amount'] ?? 0) : (is_object($txn_details) ? ($txn_details->total_paid_amount ?? 0) : 0);
            $total_amount = Price::fromArray([
              'number' => $total_paid,
              'currency_code' => $ipn_payment->currency_id ?? 'USD',
            ]);

            /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
            $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

            /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment_order */
            $payment_order = $payment_storage->create([
              'state' => 'authorization',
              'amount' => $total_amount,
              'payment_gateway' => $this->parentEntity->id(),
              'order_id' => $order->id(),
              'remote_id' => $ipn_payment->id,
              'remote_state' => (string) ($ipn_payment->status ?? ''),
            ]);
            $payment_order->save();

            $payment_order->setState('completed');
            $payment_order->save();

            $logger->info('Payment information saved successfully.<br>Order id: ' . $order->id() . '<br>Payment Type: ' . $ipn_payment->payment_type_id . '<br>Amount: ' . $total_amount);

          }
          else {

            // If it is a refund we update the payment.
            if ($ipn_payment->status == 'refunded') {
              $refunded_num = $ipn_payment->transaction_amount_refunded ?? 0;
              $total_amount = Price::fromArray([
                'number' => $refunded_num,
                'currency_code' => $ipn_payment->currency_id ?? 'USD',
              ]);

              /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
              $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
              $payment_order = $payment_storage->loadByRemoteId((string) $ipn_payment->id);
              if (!$payment_order) {
                $logger->warning('IPN refund: payment not found for remote_id @id', ['@id' => $ipn_payment->id]);
                return new JsonResponse();
              }

              $txn_details_refund = $ipn_payment->transaction_details ?? NULL;
              $total_paid_num = is_array($txn_details_refund) ? ($txn_details_refund['total_paid_amount'] ?? 0) : (is_object($txn_details_refund) ? ($txn_details_refund->total_paid_amount ?? 0) : 0);
              if ($total_paid_num > $refunded_num) {
                $payment_order->setState('partially_refunded');
              }
              else {
                $payment_order->setState('refunded');
              }

              $payment_order->setRemoteState((string) ($ipn_payment->status ?? ''));
              $payment_order->setRefundedAmount($total_amount);
              $payment_order->save();

              $logger->info('Refund.<br>Order ID: @order_id<br>Amount refunded: @amount', [
                '@order_id' => $order->id(),
                '@amount' => $total_amount->getNumber(),
              ]);
            }
          }
        }
      }
    }

    // Return empty response with 200 status code.
    return new JsonResponse();

  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request): void {
    $logger = $this->loggerFactory->get('Mercado Pago OnReturn');

    $order_checkout_pro_data = $order->getData('commerce_mercado_pago_checkout');
    if (empty($order_checkout_pro_data['preference_id'])) {
      throw new PaymentGatewayException('Preference id not found for this Checkout transaction.');
    }

    $response = $request->query->all();

    $payment_id = $response['payment_id'] ?? NULL;
    $merchant_order_id = $response['merchant_order_id'] ?? NULL;
    $status = $response['status'] ?? NULL;

    if ($payment_id === NULL || $merchant_order_id === NULL || $status === NULL) {
      $logger->error('Missing required parameters on return (payment_id, merchant_order_id, status). Response: <pre>@response</pre>', [
        '@response' => print_r($response, TRUE),
      ]);
      throw new PaymentGatewayException('Invalid return from payment provider: missing required parameters.', 0);
    }

    // Verify payment with Mercado Pago API before trusting query params (prevents
    // fraud: a malicious user could otherwise craft a return URL with fake
    // payment_id/status=approved). Never trust client-supplied payment result.
    MercadoPagoConfig::setAccessToken($this->getAccessToken());
    MercadoPagoConfig::setIntegratorId(self::INTEGRATOR_ID);

    try {
      $paymentClient = new PaymentClient();
      $mp_payment = $paymentClient->get((int) $payment_id);
    }
    catch (MPApiException $e) {
      $logger->error('Mercado Pago API error verifying payment @id: @message', [
        '@id' => $payment_id,
        '@message' => $e->getMessage(),
      ]);
      throw new PaymentGatewayException('Could not verify payment with Mercado Pago.', 0);
    }

    $verified_status = (string) ($mp_payment->status ?? '');
    if ($verified_status !== 'approved') {
      $logger->error('Payment @id not approved (status: @status)', [
        '@id' => $payment_id,
        '@status' => $verified_status !== '' ? $verified_status : 'unknown',
      ]);
      throw new PaymentGatewayException('Payment was not approved.', 0);
    }

    // Ensure the payment belongs to this order: get merchant order and check
    // external_reference matches our order id. Payment resource uses "order", not "order_id".
    $order_id_mp = NULL;
    if (isset($mp_payment->order)) {
      $order_id_mp = is_object($mp_payment->order) ? ($mp_payment->order->id ?? NULL) : ($mp_payment->order['id'] ?? NULL);
    }
    if ($order_id_mp === NULL && isset($mp_payment->order_id)) {
      $order_id_mp = is_object($mp_payment->order_id) ? ($mp_payment->order_id->id ?? NULL) : ($mp_payment->order_id ?? NULL);
    }
    if ($order_id_mp) {
      $merchantOrderClient = new MerchantOrderClient();
      try {
        $merchant_order = $merchantOrderClient->get((int) $order_id_mp);
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
      catch (MPApiException $e) {
        $logger->error('Mercado Pago API error fetching merchant order: @message', ['@message' => $e->getMessage()]);
        throw new PaymentGatewayException('Could not verify payment order.', 0);
      }
    }

    /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    // If payment was already saved with IPN method (OnNotify)
    // no need to save the payment again.
    $payment_ids = $payment_storage->getQuery()
      ->condition('order_id', $order->id())
      ->condition('remote_id', $payment_id)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (count($payment_ids) > 0) {
      $logger->info("Payment already saved with IPN notifications");
      return;
    }
    // If OnNotify method failed for some reason
    // we continue to save the payment information.
    $order->setData('commerce_mercado_pago_checkout', [
      'remote_id' => $merchant_order_id,
    ]);

    /*
    // If the user is anonymous, add their Mercado Pago e-mail to the order.
    if (empty($order->getEmail())) {
    //@todo get user email from Mercado Pago
    $user_email = $response['email']
    $order->setEmail($user_email);
    }
     */

    $payment_method = NULL;
    // If a payment method is already referenced by the order, no need to create
    // a new one.
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
      'state' => 'authorization',
      'amount' => $order->getBalance(),
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'remote_id' => $payment_id,
      'remote_state' => $status,
    ]);
    $payment->save();

    $payment->setState('completed');
    $payment->save();

    $this->messenger()->addStatus('Payment has been successfully processed.');

  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $payment_details
   *
   * @return \Drupal\commerce_payment\Entity\PaymentMethodInterface
   *   Never returns; always throws.
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details): PaymentMethodInterface {
    // Checkout Pro is offsite-only; payment methods are created during onReturn/onNotify.
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
  protected function buildCustomerProfile(OrderInterface $order): \Drupal\profile\Entity\ProfileInterface {
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
   * Builds excluded_payment_methods for the Checkout Preference from gateway config.
   *
   * Sends methods that were NOT selected so Mercado Pago excludes them. Account
   * money and wallet (digital_currency) are never excluded (always enabled).
   *
   * The Checkout Pro API only supports global exclusion: if a method is excluded,
   * it is hidden for all payment types. So we exclude a method if it is disabled
   * in any type (e.g. Visa disabled in Credit → Visa is excluded everywhere).
   *
   * @param array<string, mixed> $configuration
   *   The payment gateway configuration (country, payment_types, payment_method).
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
      // Per-type config: exclude a method if it is disabled in any type (API is global).
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

    // Never exclude account_money or digital_currency (wallet); they are always enabled.
    $excluded = array_diff($excluded, MercadoPagoPaymentMethodsMap::$alwaysEnabledPaymentTypes);

    return array_map(function ($id) {
      return ['id' => $id];
    }, array_values($excluded));
  }

  /**
   * Builds excluded_payment_types for the Checkout Preference from gateway config.
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
    // Only types that exist for this country can be excluded; account_money and digital_currency are always enabled.
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
