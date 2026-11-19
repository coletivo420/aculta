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
   * @var \Drupal\commerce_order\Entity\OrderItemInterface|null
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
   *
   * @param array<string, mixed> $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?CheckoutFlowInterface $checkout_flow = NULL,
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
        return $item->bundle() === 'donation';
      }
    );
    if (!empty($donations)) {
      $instance->donationItem = reset($donations);
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    return $this->donationItem instanceof OrderItemInterface;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   * @param array<mixed> $complete_form
   * @return array<mixed>
   */
  public function buildPaneForm(
    array $pane_form,
    FormStateInterface $form_state,
    array &$complete_form,
  ): array {
    if (!$this->donationItem instanceof OrderItemInterface) {
      return $pane_form;
    }

    /** @var \Drupal\commerce_order\Entity\OrderInterface|null $order */
    $order = $this->donationItem->getOrder();
    if ($order === NULL) {
      $order = $this->order;
    }
    $billingProfile = $order->getBillingProfile();
    $firstName = '';
    if ($billingProfile instanceof Profile && $billingProfile->hasField('address')) {
      $addressItem = $billingProfile->get('address')->first();
      if ($addressItem !== NULL) {
        $address = (array) $addressItem->getValue();
        if (!empty($address['given_name']) && is_scalar($address['given_name'])) {
          $firstName = (string) $address['given_name'] . ',';
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
      '#type' => 'component',
      '#component' => 'aculta420_v2:donation-presentation',
      '#props' => ['variant' => 'thanks'],
      '#slots' => ['content' => [
        'name' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $firstName,
        '#attributes' => ['class' => ['donation-presentation__donor']],
      ],
      'donation_clause' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('thank you for donating'),
        '#attributes' => ['class' => ['donation-presentation__clause']],
      ],
      ]],
    ];

    $pane_form['amount'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:donation-presentation',
      '#props' => ['variant' => 'amount'],
      '#slots' => ['content' => [
        'donation_amount' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#weight' => 1,
        '#value' => $donationAmount,
        '#attributes' => ['class' => ['donation-presentation__amount']],
      ],
      ]],
    ];
    $gift_type = '';
    if ($this->donationItem->hasField('field_gift_type')
    && !$this->donationItem->get('field_gift_type')->isEmpty()) {
      $typeItem = $this->donationItem->get('field_gift_type')->first();
      $rawGift = $typeItem !== NULL ? $typeItem->get('value')->getValue() : '';
      $gift_type = is_scalar($rawGift) ? (string) $rawGift : '';
    }
    if ($gift_type === 'recurring') {
      $pane_form['amount']['#slots']['content']['monthly'] = [
        '#type' => 'html_tag',
        '#weight' => 2,
        '#tag' => 'p',
        '#value' => $this->t('every month'),
        '#attributes' => ['class' => ['donation-presentation__monthly']],
      ];
    }
    if (
      $this->donationItem->hasField('field_honoree_first')
      && !$this->donationItem->get('field_honoree_first')->isEmpty()
    ) {
      $honoreeFirstItem = $this->donationItem->get('field_honoree_first')->first();
      $rawFirst = $honoreeFirstItem !== NULL ? $honoreeFirstItem->get('value')->getValue() : '';
      $honoreeFirst = is_scalar($rawFirst) ? (string) $rawFirst : '';
      $honoreeLastItem = $this->donationItem->hasField('field_honoree_last') ? $this->donationItem->get('field_honoree_last')->first() : NULL;
      $rawLast = $honoreeLastItem !== NULL && !$honoreeLastItem->isEmpty() ? $honoreeLastItem->get('value')->getValue() : '';
      $honoreeLast = is_scalar($rawLast) ? (string) $rawLast : '';
      $honoree = $honoreeLast !== '' ? $honoreeFirst . ' ' . $honoreeLast : $honoreeFirst;
      $pane_form['amount']['#slots']['content']['in_honor'] = [
        '#type' => 'html_tag',
        '#weight' => 4,
        '#tag' => 'p',
        '#value' => $this->t('in honor of @name', ['@name' => $honoree]),
        '#attributes' => ['class' => ['donation-presentation__honor']],
      ];
    }
    // Successfully built. Remove the order id from the session.
    $this->cartSession->deleteCartId((int) $order->id());
    return $pane_form;
  }

}
