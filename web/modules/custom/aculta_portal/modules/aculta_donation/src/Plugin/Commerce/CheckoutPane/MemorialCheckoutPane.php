<?php

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A custom pane for creating Donation OrderItems.
 */
#[CommerceCheckoutPane(
  id: 'commerce_memorial_pane',
  label: new TranslatableMarkup('Dedicatória'),
  display_label: new TranslatableMarkup('Dedique sua doação'),
  default_step: 'dedication',
  wrapper_element: 'fieldset',
)]
class MemorialCheckoutPane extends DonationItemPaneBase {

  /**
   * The form mode used by this pane for donation items.
   *
   * @var string
   */
  protected $formMode;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->setFormDisplay('memorial');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    $donationItem = $this->getDonationItem();
    if (
      $donationItem instanceof OrderItemInterface
      && $donationItem->hasField('field_designated')
    ) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem|null $designated */
      $designated = $donationItem->field_designated->first();
      return !is_null($designated) && $designated->value == 1;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $this->setFormDisplay($this->formMode);
    return parent::buildPaneForm($pane_form, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    if (!$this->isVisible()) {
      return [];
    }
    $viewMode = 'memorial';
    return $this->doSummaryBuild($viewMode);
  }

}
