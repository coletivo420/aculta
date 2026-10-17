<?php

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\profile\Entity\Profile;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the 'thank you' pane.
 */
#[CommerceCheckoutPane(
  id: 'donation_thank_you',
  label: new TranslatableMarkup('Obrigado'),
  default_step: 'complete',
  wrapper_element: 'div',
)]
class ThankYouPane extends CheckoutPaneBase {

  /**
   * The OrderItem that models the donation.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $donationItem;

  /**
   * The cart session is used for access to the order on the thank you pane.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  protected $cartSession;

  /**
   * Injected service.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CheckoutFlowInterface $checkout_flow = NULL,
  ) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow
    );
    $instance->cartSession = $container->get('commerce_cart.cart_session');
    $instance->currencyFormatter = $container->get(
      'commerce_price.currency_formatter'
    );
    // The order should now be populated.
    if (!empty($instance->order)) {
      // Insure an order item is set and easily found.
      $items = $instance->order->getItems();
      if (empty($items)) {
        /* @see \Drupal\aculta_donation\NewOrder::get() */
        throw new \UnexpectedValueException(
          'Order Items not properly initialized'
        );
      }
      // Only one donation OrderItem supported.
      $donations = array_filter(
        $items,
        function (OrderItemInterface $item): bool {
          return $item->bundle() == 'donation';
        }
      );
      if (!empty($donations)) {
        $instance->donationItem = reset($donations);
      }
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    if (!$this->donationItem instanceof OrderItemInterface) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(
    array $pane_form,
    FormStateInterface $form_state,
    array &$complete_form,
  ) {

    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $this->donationItem->getOrder();
    /** @var \Drupal\profile\Entity\Profile $billingProfile */
    $billingProfile = $order->getBillingProfile();
    $firstName = '';
    if ($billingProfile instanceof Profile) {
      if ($billingProfile->hasField('address')) {
        $address = $billingProfile->get('address')->first()->getValue();
        if (!empty($address['given_name'])) {
          $firstName = $address['given_name'] . ',';
        }
      }
    }

    $donationAmount = '';
    $amountFormatOptions = [
      'currency_display' => 'symbol',
      'minimum_fraction_digits' => 0,
    ];
    if ($this->donationItem->hasField(
        'field_donation_amount'
      ) && !$this->donationItem->get('field_donation_amount')->isEmpty()) {
      /** @var \Drupal\commerce_price\Plugin\Field\FieldType\PriceItem $priceItem */
      $priceItem = $this->donationItem->get('field_donation_amount')
        ->first();
      $donationAmount = $this->currencyFormatter->format(
        $priceItem->toPrice()->getNumber(),
        $priceItem->toPrice()->getCurrencyCode(),
        $amountFormatOptions
      );
    }

    $pane_form['thanks'] = [
      '#type' => 'container',
      'name' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $firstName,
        '#attributes' => ['data-portal-ui' => 'donation-donor-name'],
      ],
      'donation_clause' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('thank you for donating'),
        '#attributes' => ['data-portal-ui' => 'donation-clause'],
      ],
      '#attributes' => ['data-portal-ui' => 'donation-thanks'],
    ];

    $pane_form['amount'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'donation-amount-block'],
      'donation_amount' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#weight' => 1,
        '#value' => $donationAmount,
        '#attributes' => ['data-portal-ui' => 'donation-amount'],
      ],
    ];
    $gift_type = '';
    if ($this->donationItem->hasField('field_gift_type')
    && !$this->donationItem->field_gift_type->isEmpty()) {
      /** @var \Drupal\options\Plugin\Field\FieldType\ListStringItem $type */
      $type = $this->donationItem->field_gift_type->first();
      $gift_type = $type->value;
    }
    if ($gift_type == 'recurring') {
      $pane_form['amount']['monthly'] = [
        '#type' => 'html_tag',
        '#weight' => 2,
        '#tag' => 'p',
        '#value' => $this->t('every month'),
        '#attributes' => ['data-portal-ui' => 'donation-monthly'],
      ];
    }
    if (
      $this->donationItem->hasField('field_honoree_first')
      && !$this->donationItem->field_honoree_first->isEmpty()
    ) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\StringItem $donationToName */
      $donationToName = $this->donationItem->field_honoree_first->first();
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\StringItem|null $donationToSurname */
      $donationToSurname = $this->donationItem->hasField('field_honoree_first') ? $this->donationItem->field_honoree_last->first() : NULL;
      $honoree = is_null($donationToSurname) || $donationToSurname->isEmpty() ? $donationToName->value : $donationToName->value . ' ' . $donationToSurname->value;
      $pane_form['amount']['in_honor'] = [
        '#type' => 'html_tag',
        '#weight' => 4,
        '#tag' => 'p',
        '#value' => $this->t('in honor of @name', ['@name' => $honoree]),
        '#attributes' => ['data-portal-ui' => 'donation-in-honor'],
      ];
    }
    // Successfully built.  Remove the order id from the session.
    $this->cartSession->deleteCartId($order->id());
    return $pane_form;
  }

}
