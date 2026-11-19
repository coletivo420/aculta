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
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->setFormDisplay('memorial');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    $donationItem = $this->getDonationItem();
    if (
      $donationItem instanceof OrderItemInterface
      && $donationItem->hasField('field_designated')
    ) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem|null $designated */
      $designated = $donationItem->get('field_designated')->first();
      $val = $designated !== NULL ? $designated->get('value')->getValue() : NULL;
      return $val === 1 || $val === '1' || $val === TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   * @param array<mixed> $complete_form
   * @return array<mixed>
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $this->setFormDisplay($this->formMode);
    return parent::buildPaneForm($pane_form, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<mixed>
   */
  public function buildPaneSummary(): array {
    if (!$this->isVisible()) {
      return [];
    }
    $viewMode = 'memorial';
    return $this->doSummaryBuild($viewMode);
  }

}
