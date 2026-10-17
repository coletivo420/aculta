<?php

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
use Drupal\Core\Form\FormStateInterface;
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
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * FormDisplay object for this pane.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $formDisplay;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    // Parent constructor populates $this->order.
    $instance->moduleHandler = $container->get('module_handler');
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
    // Guard for missing order in the pane. This plugin seems to occasionally
    // get instantiated early and then re-instantiated by Commerce.
    if (!($this->order instanceof OrderInterface)) {
      return NULL;
    }
    // Sift through order items to find donation bundles.
    $donations = array_filter($this->order->getItems(), function ($item) {
      return $item->bundle() == 'donation';
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
  public function isVisible() {
    $item = $this->getDonationItem();
    return $item instanceof OrderItemInterface;
  }

  /**
   * Setter used in extending classes.
   *
   * @param string $displayMode
   *   The display mode id.
   */
  protected function setFormDisplay($displayMode = 'default') {
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
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $donationItem = $this->getDonationItem();
    if (!($this->formDisplay instanceof EntityFormDisplayInterface)) {
      // Setting this conditionally so that extending classes can explicitly
      // set this service, but we can reliably depend on it being present.
      $this->setFormDisplay();
    }
    if ($donationItem instanceof OrderItemInterface) {
      // The purpose of this pane is to display an edit form on the donation
      // item entity.  This form should not be visible if there is not such
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
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Our form comes from the entity form builder.
    $donationItem = $this->getDonationItem();
    if ($donationItem instanceof OrderItemInterface) {
      $form_state->cleanValues();
      $this->buildDonationItem($pane_form, $form_state, $donationItem);
      $this->formDisplay->validateFormValues($donationItem, $pane_form, $form_state);
      $form_state->set('donation_item', $donationItem);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
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
   * @param array $paneForm
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $donationItem
   *   The donation item we are updating.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function buildDonationItem(array $paneForm, FormStateInterface $form_state, OrderItemInterface $donationItem) {
    if (!($this->formDisplay instanceof EntityFormDisplayInterface)) {
      // Setting this conditionally so that extending classes can explicitly
      // set this service, but we can reliably depend on it being present.
      $this->setFormDisplay();
    }
    $this->formDisplay->extractFormValues($donationItem, $paneForm, $form_state);
    if ($donationItem->hasField('field_donation_amount') && !$donationItem->field_donation_amount->isEmpty()) {
      $price = $donationItem->field_donation_amount->first()->toPrice();
      $donationItem->setUnitPrice($price, TRUE);
    }
    $donationTitle = $donationItem->getTitle();
    if ($donationItem->hasField('field_gift_type')) {
      $donationTitle .= ' - ';
      $donationTitle .= $donationItem->field_gift_type->value;
      $recurringValue = NULL;
      if ($donationItem->hasField('field_recurring_begins') && $donationItem->field_gift_type->value == 'recurring') {
        $timezone = date_default_timezone_get();
        $setTime = new DrupalDateTime('+1 month', $timezone);
        // Adjust the date for storage.
        $setTime->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $recurringValue = $setTime->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
        $donationItem->field_recurring_begins->setValue(['value' => $recurringValue]);
      }
    }
    $donationItem->setTitle($donationTitle);
    // Invoke all specified builders for copying form values to entity fields.
    if (isset($paneForm['#entity_builders'])) {
      foreach ($paneForm['#entity_builders'] as $function) {
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

  /**
   * Helper method to build the pane summary given a view mode.
   *
   * @param string $viewMode
   *   The view mode to build.
   *
   * @return array
   *   The render array.
   */
  protected function doSummaryBuild($viewMode) {
    $donationItem = $this->getDonationItem();
    if (!($donationItem instanceof OrderItemInterface)) {
      return [];
    }
    $viewBuilder = $this->entityTypeManager->getViewBuilder($donationItem->getEntityTypeId());
    $entityView = $viewBuilder->view($donationItem, $viewMode, $donationItem->language()
      ->getId());
    $renderArray = $viewBuilder->build($entityView);
    $build = [
      'summary' => [
        '#theme' => 'aculta_donation_summary',
        '#content' => $renderArray,
        '#attributes' => [
          'data-portal-ui' => 'donation-summary',
          'data-donation-pane' => $this->getId(),
        ],
      ],
    ];
    return $build;
  }

}
