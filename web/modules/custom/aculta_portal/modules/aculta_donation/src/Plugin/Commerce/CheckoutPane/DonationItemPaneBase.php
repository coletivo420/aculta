<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\aculta_donation\Exception\MissingDonationItemException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Template\Attribute;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DonationItemPaneBase holds common donation pane methods.
 *
 * This class is meant to be extended, and appropriate annotations added
 * in the extending class.
 *
 * @package Drupal\aculta_donation\Plugin\Commerce\CheckoutPane
 */
class DonationItemPaneBase extends CheckoutPaneBase {

  /**
   * Injected service: Used to detect if Field Group module is installed.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * FormDisplay object for this pane.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null
   */
  protected ?EntityFormDisplayInterface $formDisplay = NULL;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   O container de injeção de dependência.
   * @param array<string, mixed> $configuration
   *   A configuração do plugin.
   * @param string $plugin_id
   *   O identificador do plugin.
   * @param mixed $plugin_definition
   *   A definição do plugin.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface|null $checkout_flow
   *   O fluxo de checkout.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    // Parent constructor populates $this->order.
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler */
    $moduleHandler = $container->get('module_handler');
    $instance->moduleHandler = $moduleHandler;
    return $instance;
  }

  /**
   * Method to retrieve the donation item from the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface|null
   *   Return the donation item or null if there are none.
   *
   * @throws \LengthException
   */
  protected function getDonationItem(): ?OrderItemInterface {
    // Sift through order items to find donation bundles.
    if ($this->order === NULL) {
      return NULL;
    }
    // Sift through order items to find donation bundles.
    $donations = array_filter($this->order->getItems(), static function (OrderItemInterface $item): bool {
      return $item->bundle() === 'donation';
    });
    // Ensure we have only 1.
    $count = count($donations);
    if ($count > 1) {
      throw new \LengthException('An order may only contain a single donation item.');
    }
    elseif ($count === 1) {
      return reset($donations);
    }
    // Count must be 0.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    $item = $this->getDonationItem();
    return $item instanceof OrderItemInterface;
  }

  /**
   * Setter used in extending classes.
   *
   * @param string $displayMode
   *   The display mode id.
   */
  protected function setFormDisplay(string $displayMode = 'default'): void {
    $donationItem = $this->getDonationItem();
    if ($donationItem instanceof OrderItemInterface) {
      $this->formDisplay = EntityFormDisplay::collectRenderDisplay(
        $donationItem,
        $displayMode
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   *   O formulário da pane.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado do formulário.
   * @param array<mixed> $complete_form
   *   O formulário completo.
   *
   * @return array<mixed>
   *   O formulário construído.
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $donationItem = $this->getDonationItem();
    if (!($this->formDisplay instanceof EntityFormDisplayInterface)) {
      // Setting this conditionally so that extending classes can explicitly
      // set this service, but we can reliably depend on it being present.
      $this->setFormDisplay();
    }
    if ($donationItem instanceof OrderItemInterface && $this->formDisplay instanceof EntityFormDisplayInterface) {
      // The purpose of this pane is to display an edit form on the donation
      // item entity. This form should not be visible if there is not such
      // an entity, however multiple lines below will fail, so setting a
      // guard.
      $pane_form['#title_display'] = 'invisible';
      // Check for ajax build.
      $input = $form_state->getUserInput();
      if (isset($input[AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER])) {
        // Render any status messages:
        $pane_form['messages'] = [
          '#type' => 'status_messages',
          '#weight' => -90,
        ];
      }
      $this->formDisplay->buildForm($donationItem, $pane_form, $form_state);
    }
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   *   O formulário da pane.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado do formulário.
   * @param array<mixed> $complete_form
   *   O formulário completo.
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    // Our form comes from the entity form builder.
    $donationItem = $this->getDonationItem();
    if (!($this->formDisplay instanceof EntityFormDisplayInterface)) {
      $this->setFormDisplay();
    }
    $formDisplay = $this->formDisplay;
    if ($donationItem instanceof OrderItemInterface && $formDisplay instanceof EntityFormDisplayInterface) {
      $form_state->cleanValues();
      $this->buildDonationItem($pane_form, $form_state, $donationItem);
      $formDisplay->validateFormValues($donationItem, $pane_form, $form_state);
      $form_state->set('donation_item', $donationItem);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   *   O formulário da pane.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado do formulário.
   * @param array<mixed> $complete_form
   *   O formulário completo.
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $donationItem = $form_state->get('donation_item');
    if ($donationItem instanceof OrderItemInterface) {
      $donationItem->save();
    }
    else {
      // Allow extending panes to catch an Exception if desired to deal with
      // a missing donation OrderItem.
      throw new MissingDonationItemException('Instance of OrderItemInterface not stored as `donation_item` in FormState');
    }
  }

  /**
   * Builds an updated OrderItem object based upon the submitted form values.
   *
   * Based on \Drupal\inline_entity_form\Form\EntityInlineForm::buildEntity:
   *
   * @param array<string, mixed> $paneForm
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $donationItem
   *   The donation item we are updating.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  /**
   * Builds an updated OrderItem object based upon the submitted form values.
   *
   * @param array<mixed> $paneForm
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $donationItem
   *   The donation item we are updating.
   */
  protected function buildDonationItem(array $paneForm, FormStateInterface $form_state, OrderItemInterface $donationItem): void {
    if (!($this->formDisplay instanceof EntityFormDisplayInterface)) {
      // Setting this conditionally so that extending classes can explicitly
      // set this service, but we can reliably depend on it being present.
      $this->setFormDisplay();
    }
    if ($this->formDisplay instanceof EntityFormDisplayInterface) {
      $this->formDisplay->extractFormValues($donationItem, $paneForm, $form_state);
    }
    if ($donationItem->hasField('field_donation_amount') && !$donationItem->get('field_donation_amount')->isEmpty()) {
      $firstPriceItem = $donationItem->get('field_donation_amount')->first();
      /** @var \Drupal\commerce_price\Plugin\Field\FieldType\PriceItem|null $firstPriceItem */
      if ($firstPriceItem !== NULL) {
        $price = $firstPriceItem->toPrice();
        $donationItem->setUnitPrice($price, TRUE);
      }
    }
    $donationTitle = (string) $donationItem->getTitle();
    if ($donationItem->hasField('field_gift_type') && !$donationItem->get('field_gift_type')->isEmpty()) {
      $giftVal = $donationItem->get('field_gift_type')->value;
      $giftTypeStr = is_scalar($giftVal) || $giftVal instanceof \Stringable ? (string) $giftVal : '';
      $donationTitle .= ' - ' . $giftTypeStr;
      if ($donationItem->hasField('field_recurring_begins') && $giftTypeStr === 'recurring') {
        $timezone = date_default_timezone_get();
        $setTime = new DrupalDateTime('+1 month', $timezone);
        // Adjust the date for storage.
        $setTime->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $recurringValue = $setTime->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
        $donationItem->get('field_recurring_begins')->setValue(['value' => $recurringValue]);
      }
    }
    $donationItem->setTitle($donationTitle);
    // Invoke all specified builders for copying form values to entity fields.
    if (isset($paneForm['#entity_builders']) && is_array($paneForm['#entity_builders'])) {
      foreach ($paneForm['#entity_builders'] as $function) {
        if (is_callable($function)) {
          call_user_func_array(
            $function,
            [
              $donationItem->getEntityTypeId(),
              $donationItem,
              &$paneForm,
              &$form_state,
            ]
          );
        }
      }
    }
  }

  /**
   * Helper method to build the pane summary given a view mode.
   *
   * @param string $viewMode
   *   The view mode to build.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  protected function doSummaryBuild(string $viewMode): array {
    $donationItem = $this->getDonationItem();
    if (!($donationItem instanceof OrderItemInterface)) {
      return [];
    }
    $viewBuilder = $this->entityTypeManager->getViewBuilder($donationItem->getEntityTypeId());
    $renderArray = $viewBuilder->view($donationItem, $viewMode, $donationItem->language()->getId());
    return [
      'summary' => [
        '#type' => 'component',
        '#component' => 'aculta420_v2:donation-presentation',
        '#props' => [
          'variant' => 'summary',
          'attributes' => new Attribute(['data-donation-pane' => $this->getId()]),
        ],
        '#slots' => ['content' => $renderArray],
      ],
    ];
  }

}
