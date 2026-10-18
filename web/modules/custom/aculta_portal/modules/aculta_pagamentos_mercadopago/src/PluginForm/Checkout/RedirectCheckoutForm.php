<?php

namespace Drupal\aculta_pagamentos_mercadopago\PluginForm\Checkout;

use Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway\CheckoutPro;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\commerce_price\Price;
use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Off-site form for Mercado Pago Checkout.
 *
 * This is provided as a fallback when no "review" step is present in Checkout.
 */
class RedirectCheckoutForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs the offsite payment form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory (available for future use, e.g. module config).
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger, FileUrlGeneratorInterface $file_url_generator, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->fileUrlGenerator = $file_url_generator;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // Parent requires return type static; this form is not extended.
    // @phpstan-ignore-next-line new.static
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('file_url_generator'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @return array<string, mixed>
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    $payment_gateway = $payment->getPaymentGateway();
    if ($payment_gateway === NULL) {
      $this->messenger->addError($this->t('Payment gateway is missing.'));
      return $form;
    }
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    $configuration = $payment_gateway_plugin->getConfiguration();

    // Initialize the SDK with the configured access token before any API call.
    $mode = $configuration['mode'] ?? 'test';
    $access_token = $mode === 'test' ? ($configuration['access_token_test'] ?? '')
      : ($mode === 'stage' ? ($configuration['access_token_stage'] ?? '') : ($configuration['access_token_prod'] ?? ''));
    if (empty($access_token)) {
      $this->messenger->addError($this->t('Mercado Pago is not configured. Please contact the administrator.'));
      return $form;
    }
    MercadoPagoConfig::setAccessToken($access_token);
    MercadoPagoConfig::setIntegratorId(CheckoutPro::INTEGRATOR_ID);

    $order_id = $payment->getOrderId();
    $order = $payment->getOrder();
    if ($order === NULL) {
      $this->messenger->addError($this->t('Order is missing.'));
      return $form;
    }

    $billing_profile = $order->getBillingProfile();
    if (!$billing_profile || $billing_profile->get('address')->isEmpty()) {
      $this->messenger->addError($this->t('Billing information is required to proceed.'));
      return $form;
    }
    $address = $billing_profile->get('address')->first();
    if ($address === NULL || !$address instanceof AddressItem) {
      $this->messenger->addError($this->t('Billing address is missing.'));
      return $form;
    }

    $payer = [
      'name' => $address->getGivenName(),
      'surname' => $address->getFamilyName(),
      'email' => $order->getEmail() ?: '',
      'address' => [
        'zip_code' => $address->getPostalCode() ?: '',
        'street_name' => $address->getAddressLine1() . ' / ' . $address->getLocality() . ' ' . $address->getAdministrativeArea() . ' ' . $address->getCountryCode() . ' / ',
      ],
    ];

    $items = [];
    foreach ($order->getItems() as $order_item) {
      $unit_price = $order_item->getUnitPrice();
      if ($unit_price === NULL) {
        continue;
      }
      $item = [
        'title' => $order_item->getTitle(),
        'quantity' => (int) $order_item->getQuantity(),
        'unit_price' => (float) $unit_price->getNumber(),
        'currency_id' => $unit_price->getCurrencyCode(),
      ];
      $purchased = $order_item->getPurchasedEntity();
      if ($purchased !== NULL) {
        if (method_exists($purchased, 'getSku')) {
          $sku = $purchased->getSku();
          if ($sku !== NULL && $sku !== '') {
            $item['id'] = mb_substr((string) $sku, 0, 256);
          }
        }
        $description = $this->getProductDescription($purchased);
        if ($description !== '') {
          $item['description'] = mb_substr($description, 0, 256);
        }
        /*
        $picture_url = $this->getProductImageUrl($purchased);
        if ($picture_url !== '') {
          $item['picture_url'] = mb_substr($picture_url, 0, 600);
        }*/

        // Common image field names in Drupal Commerce.
        $image_fields = ['field_images', 'images', 'field_image'];
        $file_entity = NULL;

        foreach ($image_fields as $field_name) {
          if ($purchased->hasField($field_name) && !$purchased->get($field_name)->isEmpty()) {
            $file_entity = $purchased->get($field_name)->entity;
            break;
          }
        }

        // If not found in Variation, try the parent Product.
        if (!$file_entity && method_exists($purchased, 'getProduct')) {
          $product = $purchased->getProduct();
          if ($product !== NULL) {
            foreach ($image_fields as $field_name) {
              if ($product->hasField($field_name) && !$product->get($field_name)->isEmpty()) {
                $file_entity = $product->get($field_name)->entity;
                break;
              }
            }
          }
        }

        if ($file_entity instanceof \Drupal\file\FileInterface) {
          $file_uri = $file_entity->getFileUri();
          if ($file_uri !== NULL && $file_uri !== '') {
            $picture_url = $this->fileUrlGenerator->generateAbsoluteString($file_uri);
            if ($picture_url !== '') {
              // Mercado Pago limit is usually 256/600 characters depending on version.
              $item['picture_url'] = mb_substr($picture_url, 0, 600);
            }
          }
        }
      }
      $items[] = $item;
    }

    $notification_url = Url::fromRoute('commerce_payment.notify', [
      'commerce_payment_gateway' => $payment_gateway->id(),
    ], ['absolute' => TRUE]);

    $is_external_redirect = ($configuration['redirect_mode'] ?? 'redirect') === 'external_redirect';

    // For popup/modal flow, Mercado Pago redirects to our intermediate page so we
    // can close the popup and redirect the opener (avoids leaving two tabs open).
    if ($is_external_redirect) {
      $popup_return = Url::fromRoute('aculta_pagamentos_mercadopago.checkout_popup_return', [], [
        'absolute' => TRUE,
        'query' => ['redirect' => $form['#return_url']],
      ])->toString();
      $popup_cancel = Url::fromRoute('aculta_pagamentos_mercadopago.checkout_popup_return', [], [
        'absolute' => TRUE,
        'query' => ['redirect' => $form['#cancel_url']],
      ])->toString();
      $url_success = $popup_return;
      $url_pending = $popup_return;
      $url_failure = $popup_cancel;
    }
    else {
      $url_success = $form['#return_url'];
      $url_pending = $form['#cancel_url'];
      $url_failure = $form['#cancel_url'];
    }

    $request = [
      'items' => $items,
      'payer' => $payer,
      'notification_url' => $notification_url->toString() . '?source_news=ipn',
      'external_reference' => (string) $order_id,
      'binary_mode' => TRUE,
      'back_urls' => [
        'success' => $url_success,
        'pending' => $url_pending,
        'failure' => $url_failure,
      ],
      'payment_methods' => [
        'excluded_payment_methods' => CheckoutPro::getExcludedPaymentMethodsForPreference($configuration),
        'excluded_payment_types' => CheckoutPro::getExcludedPaymentTypesForPreference($configuration),
        'installments' => max(1, min(12, (int) ($configuration['max_installments'] ?? 12))),
        'default_installments' => 1,
      ],
    ];

    if (!empty($configuration['debug_logging'])) {
      $this->loggerFactory->get('Mercado Pago debug')->debug('Request sent: <pre>' . print_r($request, TRUE) . '</pre>');
    }

    $request['auto_return'] = 'approved';

    $order_total = $order->getTotalPrice();
    if ($order_total === NULL) {
      $this->messenger->addError($this->t('Order total is missing.'));
      return $form;
    }
    $currency_code = $order_total->getCurrencyCode();
    $shipping_amount = new Price('0', $currency_code);

    foreach ($order->collectAdjustments() as $adjustment) {
      if ($adjustment->isIncluded()) {
        continue;
      }
      if ($adjustment->getType() == 'shipping') {
        $shipping_amount = $shipping_amount->add($adjustment->getAmount());
      }
    }

    $request['shipments'] = [
      'mode' => 'not_specified',
      'cost' => (float) $shipping_amount->getNumber(),
    ];

    try {
      $preferenceClient = new PreferenceClient();
      $preference = $preferenceClient->create($request);
    }
    catch (MPApiException $e) {
      $this->loggerFactory->get('aculta_pagamentos_mercadopago')->error('Mercado Pago API failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('Communication with Mercado Pago API failed. Please contact the administrator.'));
      return $form;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('aculta_pagamentos_mercadopago')->error('Mercado Pago failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('Communication with Mercado Pago failed. Please contact the administrator.'));
      return $form;
    }

    $preference_id = $preference->id;

    // Use init_point for redirect; sandbox_init_point may be unreliable in test mode.
    $form['#action'] = $preference->init_point;

    $order->setData('commerce_mercado_pago_checkout', [
      'preference_id' => $preference_id,
    ]);

    $order->save();

    $mode = $configuration['mode'] ?? 'test';
    $public_key = $mode === 'test' ? ($configuration['public_key_test'] ?? '')
      : ($mode === 'stage' ? ($configuration['public_key_stage'] ?? '') : ($configuration['public_key_prod'] ?? ''));

    $payment_amount = $payment->getAmount();
    if ($payment_amount === NULL) {
      $this->messenger->addError($this->t('Payment amount is missing.'));
      return $form;
    }
    $redirect_url = $preference->init_point ?? '';
    if ($redirect_url === '') {
      $this->messenger->addError($this->t('Mercado Pago redirect URL is missing.'));
      return $form;
    }
    $data = [
      'return' => $form['#return_url'],
      'cancel' => $form['#cancel_url'],
      'preference_id' => $preference_id,
      'public_key' => $public_key,
      'redirect_url' => $redirect_url,
      'total' => $payment_amount->getNumber(),
    ];

    if ($is_external_redirect) {
      // Open checkout in a popup window (modal-like). User clicks the button;
      // we open window.open() so the popup can be closed on return and only one
      // tab remains. Uses official init_point; return goes via our intermediate
      // route that closes the popup and redirects the opener.
      $form['#process'][] = [static::class, 'processExternalRedirectForm'];
      $form['#attached']['library'][] = 'aculta_pagamentos_mercadopago/checkout_popup';
      $form['#attached']['drupalSettings']['commerceMercadoPago']['checkoutPopup'] = [
        'initPoint' => $redirect_url,
      ];
      $form['commerce_message'] = [
        '#markup' => '<div class="checkout-help">' . $this->t('Click the button below to proceed to Mercado Pago (opens in a popup window). After payment, the window will close automatically.') . '</div>',
        '#weight' => -10,
      ];

      return $form;
    }

    return $this->buildRedirectForm(
      $form,
      $form_state,
      $redirect_url,
      $data,
      'post'
    );
  }

  /**
   * Process callback: prepares the form for external redirect (popup).
   *
   * Sets form action to # so the form does not submit; JS opens init_point in a
   * popup instead. Makes the submit button visible so the user triggers the
   * popup by click (avoids browser blocking). Does not add payment-redirect-form
   * so Commerce's offsite_redirect.js does not run.
   *
   * @param array<string, mixed> $form
   * @param array<string, mixed> $complete_form
   *
   * @return array<string, mixed>
   */
  public static function processExternalRedirectForm(array $form, FormStateInterface $form_state, array &$complete_form): array {
    $complete_form['#action'] = '#';
    $complete_form['#attributes']['class'][] = 'mercado-pago-checkout-popup';
    $complete_form['actions']['#access'] = TRUE;
    foreach (Element::children($complete_form['actions']) as $element_name) {
      $complete_form['actions'][$element_name]['#access'] = TRUE;
    }
    return $form;
  }

  /**
   * Gets a plain-text description from a purchasable entity (e.g. product variation).
   *
   * Tries 'body' and 'description' on the entity and, if present, on its parent
   * product. Mercado Pago accepts item description up to 256 characters.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The purchased entity (e.g. product variation).
   *
   * @return string
   *   Plain text description, or empty string if none found.
   */
  protected function getProductDescription($entity): string {
    $candidates = ['body', 'description'];
    foreach ($candidates as $field_name) {
      if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
        $value = $entity->get($field_name)->first();
        if ($value !== NULL) {
          $raw = $value->getValue();
          $text_value = isset($raw['value']) ? trim((string) $raw['value']) : '';
          if ($text_value !== '') {
            $text = trim(strip_tags($text_value));
            if ($text !== '') {
              return $text;
            }
          }
        }
      }
    }
    if (method_exists($entity, 'getProduct')) {
      $product = $entity->getProduct();
      if ($product !== NULL && $product->hasField('body') && !$product->get('body')->isEmpty()) {
        $value = $product->get('body')->first();
        if ($value !== NULL) {
          $raw = $value->getValue();
          $text_value = isset($raw['value']) ? trim((string) $raw['value']) : '';
          if ($text_value !== '') {
            $text = trim(strip_tags($text_value));
            if ($text !== '') {
              return $text;
            }
          }
        }
      }
      if ($product !== NULL && $product->hasField('description') && !$product->get('description')->isEmpty()) {
        $value = $product->get('description')->first();
        if ($value !== NULL) {
          $raw = $value->getValue();
          $text_value = isset($raw['value']) ? trim((string) $raw['value']) : '';
          if ($text_value !== '') {
            $text = trim(strip_tags($text_value));
            if ($text !== '') {
              return $text;
            }
          }
        }
      }
    }
    return '';
  }

  /**
   * Gets the absolute URL of the first product image from a purchasable entity.
   *
   * Tries common image field names on the entity (e.g. variation) and on its
   * parent product. Mercado Pago accepts picture_url up to 600 characters.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The purchased entity (e.g. product variation).
   *
   * @return string
   *   Absolute URL of the image, or empty string if none found.
   */
  protected function getProductImageUrl($entity): string {
    $image_field_names = ['field_image', 'image', 'field_product_image', 'field_images'];
    foreach ($image_field_names as $field_name) {
      $url = $this->getImageUrlFromField($entity, $field_name);
      if ($url !== '') {
        return $url;
      }
    }
    if (method_exists($entity, 'getProduct')) {
      $product = $entity->getProduct();
      if ($product !== NULL) {
        foreach ($image_field_names as $field_name) {
          $url = $this->getImageUrlFromField($product, $field_name);
          if ($url !== '') {
            return $url;
          }
        }
      }
    }
    return '';
  }

  /**
   * Gets the absolute URL of the first image from an entity's image field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity that may have the field.
   * @param string $field_name
   *   The field name (e.g. field_image).
   *
   * @return string
   *   Absolute URL, or empty string if the field is empty or not an image ref.
   */
  protected function getImageUrlFromField($entity, string $field_name): string {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return '';
    }
    $item = $entity->get($field_name)->first();
    if ($item === NULL || !$item instanceof \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem) {
      return '';
    }
    $file = $item->entity;
    if ($file === NULL || !$file instanceof \Drupal\file\FileInterface) {
      return '';
    }
    $uri = $file->getFileUri();
    if ($uri === NULL || $uri === '') {
      return '';
    }
    try {
      return $this->fileUrlGenerator->generateAbsoluteString($uri);
    }
    catch (\Exception $e) {
      return '';
    }
  }

}
