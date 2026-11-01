<?php

namespace Drupal\aculta_donation\Plugin\Field\FieldFormatter;

use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter to display a donation amount.
 */
#[FieldFormatter(
  id: 'commerce_donation_summary_formatter',
  label: new TranslatableMarkup('Resumo da doação'),
  field_types: ['commerce_price'],
)]
class DonationSummaryFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Injected service.
   *
   * @var \Drupal\commerce_store\CurrentStore
   */
  protected $store;

  /**
   * Injected service.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found

  /**
   * {@inheritdoc}
   */
  final public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    // @see https://phpstan.org/blog/solving-phpstan-error-unsafe-usage-of-new-static
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings']
    );
    $instance->store = $container->get('commerce_store.current_store');
    $instance->currencyFormatter = $container->get('commerce_price.currency_formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = ['#markup' => $this->viewValue($item)];
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    if (!$item instanceof PriceItem) {
      throw new \UnexpectedValueException('DonationLevelFormatter called on incompatible field type');
    }
    $donationAmount = $item->toPrice();
    $isMonthly = FALSE;
    $entity = $item->getEntity();
    if ($entity->hasField('field_gift_type') && !$entity->field_gift_type->isEmpty()) {
      $isMonthly = $entity->field_gift_type->first()->value == 'recurring';
    }
    $formatOptions = [
      'currency_display' => 'symbol',
      'minimum_fraction_digits' => 0,
    ];
    $amount = $this->currencyFormatter->format($donationAmount->getNumber(), $this->store->getStore()->getDefaultCurrencyCode(), $formatOptions);
    if ($isMonthly) {
      $summary = $this->t('Donating @amount/mo', ['@amount' => $amount]);
    }
    else {
      $summary = $this->t('Donating @amount', ['@amount' => $amount]);
    }
    return $summary;
  }

}
