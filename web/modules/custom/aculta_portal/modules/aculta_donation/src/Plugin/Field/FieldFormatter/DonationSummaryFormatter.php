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
 *
 * @extends \Drupal\Core\Field\FormatterBase<\Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>>
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
   *
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param array<mixed> $settings
   * @param string $label
   * @param string $view_mode
   * @param array<mixed> $third_party_settings
   */
  final public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    // @see https://phpstan.org/blog/solving-phpstan-error-unsafe-usage-of-new-static
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    \assert($configuration['field_definition'] instanceof FieldDefinitionInterface);
    $settings = isset($configuration['settings']) && is_array($configuration['settings']) ? $configuration['settings'] : [];
    $thirdParty = isset($configuration['third_party_settings']) && is_array($configuration['third_party_settings']) ? $configuration['third_party_settings'] : [];
    $rawLabel = $configuration['label'] ?? '';
    $rawViewMode = $configuration['view_mode'] ?? '';
    $label = is_scalar($rawLabel) ? (string) $rawLabel : '';
    $viewMode = is_scalar($rawViewMode) ? (string) $rawViewMode : '';
    $instance = new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $settings,
      $label,
      $viewMode,
      $thirdParty
    );
    $instance->store = $container->get('commerce_store.current_store');
    $instance->currencyFormatter = $container->get('commerce_price.currency_formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   * @return array<int, array<string, mixed>>
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
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
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item): TranslatableMarkup {
    if (!$item instanceof PriceItem) {
      throw new \UnexpectedValueException('DonationLevelFormatter called on incompatible field type');
    }
    $donationAmount = $item->toPrice();
    $isMonthly = FALSE;
    $entity = $item->getEntity();
    if ($entity->hasField('field_gift_type') && !$entity->get('field_gift_type')->isEmpty()) {
      $giftItem = $entity->get('field_gift_type')->first();
      $isMonthly = $giftItem !== NULL && $giftItem->get('value')->getValue() === 'recurring';
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
