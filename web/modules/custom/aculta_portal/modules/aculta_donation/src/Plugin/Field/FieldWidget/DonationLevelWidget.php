<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Plugin\Field\FieldWidget;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * A price field widget for donation levels.
 */
#[FieldWidget(
  id: 'commerce_donation_flow_level_widget',
  label: new TranslatableMarkup('Nível de doação'),
  field_types: ['commerce_price'],
)]
class DonationLevelWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Injected service.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected CurrentStoreInterface $store;

  /**
   * Injected service.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected CurrencyFormatterInterface $currencyFormatter;

  /**
   * Level keys.
   *
   * @var array<int, string>
   */
  protected array $levels;

  /**
   * The current radio options.
   *
   * @var array<string, string>
   */
  protected array $options = [];

  /**
   * The current default value.
   *
   * @var string|null
   */
  protected ?string $defaultValue = NULL;

  // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found

  /**
   * {@inheritdoc}
   *
   * @param string $plugin_id
   *   O ID do plugin.
   * @param mixed $plugin_definition
   *   A definição do plugin.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   A definição do campo.
   * @param array<string, mixed> $settings
   *   As configurações do widget.
   * @param array<string, mixed> $third_party_settings
   *   Configurações de terceiros.
   */
  final public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings) {
    // @see https://phpstan.org/blog/solving-phpstan-error-unsafe-usage-of-new-static
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   O container de serviços.
   * @param array<string, mixed> $configuration
   *   A configuração do plugin.
   * @param string $plugin_id
   *   O ID do plugin.
   * @param mixed $plugin_definition
   *   A definição do plugin.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition */
    $fieldDefinition = $configuration['field_definition'];
    /** @var array<string, mixed> $settings */
    $settings = isset($configuration['settings']) && is_array($configuration['settings']) ? $configuration['settings'] : [];
    /** @var array<string, mixed> $thirdPartySettings */
    $thirdPartySettings = isset($configuration['third_party_settings']) && is_array($configuration['third_party_settings']) ? $configuration['third_party_settings'] : [];

    $instance = new static(
      $plugin_id,
      $plugin_definition,
      $fieldDefinition,
      $settings,
      $thirdPartySettings,
    );
    /** @var \Drupal\commerce_store\CurrentStoreInterface $store */
    $store = $container->get('commerce_store.current_store');
    $instance->store = $store;
    /** @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $formatter */
    $formatter = $container->get('commerce_price.currency_formatter');
    $instance->currencyFormatter = $formatter;
    $instance->levels = [
      'level_1',
      'level_2',
      'level_3',
      'level_4',
      'level_5',
      'custom_amount',
    ];
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public static function defaultSettings(): array {
    // A moeda vazia significa "moeda padrão da loja atual", resolvida por
    // currencyCode() com o serviço injetado (defaultSettings é estático).
    return [
      'currency' => '',
      'levels' => [],
    ] + parent::defaultSettings();
  }

  /**
   * Returns the currency code, falling back to the current store's default.
   */
  protected function currencyCode(): string {
    $currencySetting = $this->getSetting('currency');
    $currency = is_scalar($currencySetting) || $currencySetting instanceof \Stringable ? (string) $currencySetting : '';
    return $currency !== '' ? $currency : (string) $this->store->getStore()->getDefaultCurrencyCode();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   O formulário pai.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @return array<string, mixed>
   *   O array renderizável do formulário de configurações.
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed> $levels */
    $levels = is_array($this->getSetting('levels')) ? $this->getSetting('levels') : [];
    $types = [
      'single' => $this->t('Single Donation'),
      'monthly' => $this->t('Monthly Donation'),
    ];
    $form['levels'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Preset donation levels'),
      '#description' => $this->t(
        'These are fixed amounts offered for choice to a user.'
      ),
    ];
    foreach ($types as $type => $label) {
      $form['levels'][$type] = [
        '#type' => 'fieldset',
        '#title' => $label,
      ];
      $typeLevels = isset($levels[$type]) && is_array($levels[$type]) ? $levels[$type] : [];
      foreach ($this->levels as $level) {
        if ($level === 'custom_amount') {
          continue;
        }
        $defaultValue = isset($typeLevels[$level]) && is_numeric($typeLevels[$level]) ? (float) $typeLevels[$level] : 5;
        $form['levels'][$type][$level] = [
          '#type' => 'number',
          '#title' => $this
            ->t('Amount (@level):', ['@level' => $level]),
          '#default_value' => $defaultValue,
          '#min' => 5,
          '#step' => 5,
        ];
      }
    }
    return $form;
  }

  /**
   * Helper function for filtering empty values.
   *
   * @param mixed $value
   *   The value to check.
   *
   * @return bool
   *   Returns true if the variable stores a value.
   */
  public static function filterEmptyAmount(mixed $value): bool {
    return !empty($value);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, \Drupal\Core\StringTranslation\TranslatableMarkup|string>
   */
  public function settingsSummary(): array {
    $class = get_class($this);
    /** @var array<int, \Drupal\Core\StringTranslation\TranslatableMarkup|string> $summary */
    $summary = parent::settingsSummary();
    /** @var array<string, mixed> $levels */
    $levels = is_array($this->getSetting('levels')) ? $this->getSetting('levels') : [];
    $singleLevels = isset($levels['single']) && is_array($levels['single']) ? $levels['single'] : [];
    $monthlyLevels = isset($levels['monthly']) && is_array($levels['monthly']) ? $levels['monthly'] : [];

    $singleFiltered = array_map(static fn(mixed $v): string => is_scalar($v) || $v instanceof \Stringable ? (string) $v : '', array_filter($singleLevels, [$class, 'filterEmptyAmount']));
    $monthlyFiltered = array_map(static fn(mixed $v): string => is_scalar($v) || $v instanceof \Stringable ? (string) $v : '', array_filter($monthlyLevels, [$class, 'filterEmptyAmount']));

    $singleValues = implode(', ', $singleFiltered);
    $monthlyValues = implode(', ', $monthlyFiltered);
    $summary[] = $this->t('Single: @values', ['@values' => $singleValues]);
    $summary[] = $this->t('Monthly: @values', ['@values' => $monthlyValues]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   A lista de itens do campo.
   * @param int|string $delta
   *   O delta do item.
   * @param array<string, mixed> $element
   *   O elemento do formulário.
   * @param array<string, mixed> $form
   *   O formulário completo.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @return array<string, mixed>
   *   O elemento renderizável do widget.
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ): array {
    $this->init($items, $delta, $element, $form, $form_state);
    $customLevelId = $this->getCustomLevelId();
    $element = [
      'donation_level' => [
        '#type' => 'fieldset',
        '#title' => $this
          ->t('Amount'),
        '#required' => $element['#required'] ?? FALSE,
        '#attributes' => [
          'data-donation-level' => 'fieldset',
        ],
        'value' => [
          '#type' => 'radios',
          '#default_value' => empty($this->defaultValue) ? NULL : $this->defaultValue,
          '#options' => $this->options,
          '#attributes' => [
            'data-donation-level' => 'options',
          ],
        ],
      ],
    ];
    if (!empty($customLevelId)) {
      $label = $this->t('Other Amount');
      $firstItem = $items->first();
      $incomingValue = $firstItem !== NULL ? $firstItem->getValue() : [];
      $useCustom = $this->defaultValue === $customLevelId;
      $defaultCustomAmount = NULL;
      if ($useCustom && is_array($incomingValue) && isset($incomingValue['number'])) {
        $numVal = is_scalar($incomingValue['number']) || $incomingValue['number'] instanceof \Stringable ? (string) $incomingValue['number'] : '0';
        $defaultCustomAmount = $this->formatAmount($numVal, FALSE, FALSE);
      }
      $element['donation_level']['amount'] = [
        '#type' => 'number',
        '#title' => $label,
        '#placeholder' => $label,
        '#title_display' => 'before',
        '#step' => 'any',
        '#min' => 5,
        '#default_value' => $defaultCustomAmount,
        '#attributes' => [
          'data-donation-level' => 'custom-amount',
        ],
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<int|string, mixed> $values
   *   Os valores brutos enviados pelo formulário.
   * @param array<string, mixed> $form
   *   O formulário completo.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @return array<int|string, mixed>
   *   Os valores preparados para salvar na entidade.
   */
  public function massageFormValues(
    array $values,
    array $form,
    FormStateInterface $form_state,
  ): array {
    $customLevelId = $this->getCustomLevelId();
    $rawGiftType = $form_state->getValue([
      'commerce_donation_pane',
      'field_gift_type',
    ]);
    $gift_type = is_array($rawGiftType) ? reset($rawGiftType) : $rawGiftType;
    $firstGiftType = is_array($gift_type) ? reset($gift_type) : $gift_type;
    $isMonthly = is_scalar($firstGiftType) && (string) $firstGiftType === 'recurring';
    $options = $this->calculateOptions($form_state, $isMonthly);

    foreach ($values as &$item) {
      if (!is_array($item)) {
        continue;
      }
      $donationLevel = isset($item['donation_level']) && is_array($item['donation_level']) ? $item['donation_level'] : [];
      $selectedValue = is_scalar($donationLevel['value'] ?? NULL) ? (string) $donationLevel['value'] : '';

      if ($selectedValue === $customLevelId) {
        // A custom amount is used.
        $rawAmount = $donationLevel['amount'] ?? 0;
        $amountStr = is_scalar($rawAmount) || $rawAmount instanceof \Stringable ? (string) $rawAmount : '0';
        if ($amountStr === '') {
          $amountStr = '0';
        }
        $value = [
          'number' => $amountStr,
          'currency_code' => $this->currencyCode(),
        ];
      }
      else {
        // Get amount from the selected option.
        $optionLabel = isset($options[$selectedValue]) ? $options[$selectedValue] : '';
        $number = preg_replace('/\D/', '', $optionLabel) ?? '';
        $value = [
          'number' => $number,
          'currency_code' => $this->currencyCode(),
        ];
      }
      $item = $value;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $element
   *   O elemento do formulário.
   * @param \Symfony\Component\Validator\ConstraintViolationInterface $error
   *   A violação de restrição.
   * @param array<string, mixed> $form
   *   O formulário completo.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @return array<string, mixed>|bool
   *   O elemento afetado pelo erro ou FALSE para ignorar.
   */
  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $element
   *   O elemento do formulário.
   * @param \Symfony\Component\Validator\ConstraintViolationInterface $error
   *   A violação de restrição.
   * @param array<string, mixed> $form
   *   O formulário completo.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @return array<mixed>|false
   *   O elemento afetado pelo erro ou FALSE para ignorar.
   */
  public function errorElement(
    array $element,
    ConstraintViolationInterface $error,
    array $form,
    FormStateInterface $form_state,
  ): array|false {
    $donationLevel = isset($element['donation_level']) && is_array($element['donation_level'])
      ? $element['donation_level']
      : [];
    $propertyPath = property_exists($error, 'arrayPropertyPath') && is_array($error->arrayPropertyPath)
      ? $error->arrayPropertyPath
      : explode('.', $error->getPropertyPath());
    $error_element = NestedArray::getValue(
      $donationLevel,
      $propertyPath,
    );
    return is_array($error_element) ? $error_element : FALSE;
  }

  /**
   * Utility function to build the options array and default value for the form.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   A lista de itens do campo.
   * @param int|string $delta
   *   O delta do item.
   * @param array<string, mixed> $element
   *   O elemento do formulário.
   * @param array<string, mixed> $form
   *   O formulário completo.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado atual do formulário.
   */
  protected function init(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $entity = $items->getEntity();
    $isMonthly = FALSE;
    $firstItem = $items->first();
    $incomingValue = $firstItem !== NULL ? $firstItem->getValue() : [];
    if (!is_array($incomingValue)) {
      $incomingValue = [];
    }
    $userInput = $form_state->getUserInput();
    if (empty($userInput) && $entity->hasField('field_gift_type')) {
      $giftTypeField = $entity->get('field_gift_type');
      if (!$giftTypeField->isEmpty()) {
        $firstGift = $giftTypeField->first();
        $giftVal = $firstGift !== NULL ? $firstGift->get('value')->getValue() : NULL;
        $isMonthly = is_scalar($giftVal) && (string) $giftVal === 'recurring';
      }
    }
    elseif (!empty($userInput)) {
      // Clear any set values as we are changing the options.
      $incomingValue = [];
      if (isset($userInput['commerce_donation_pane']) && is_array($userInput['commerce_donation_pane'])) {
        if (isset($userInput['commerce_donation_pane']['field_donation_amount']) && is_array($userInput['commerce_donation_pane']['field_donation_amount'])) {
          if (isset($userInput['commerce_donation_pane']['field_donation_amount'][0]) && is_array($userInput['commerce_donation_pane']['field_donation_amount'][0])) {
            $userInput['commerce_donation_pane']['field_donation_amount'][0]['donation_level'] = NULL;
            $form_state->setUserInput($userInput);
          }
        }
        if (isset($userInput['commerce_donation_pane']['field_gift_type'])) {
          $giftInput = $userInput['commerce_donation_pane']['field_gift_type'];
          $isMonthly = (is_scalar($giftInput) || $giftInput instanceof \Stringable) && (string) $giftInput === 'recurring';
        }
      }
    }
    $this->options = $this->calculateOptions($form_state, $isMonthly);
    /** @var array<string, mixed> $incomingArray */
    $incomingArray = $incomingValue;
    $default = $this->calculateDefault($form_state, $incomingArray, $isMonthly);
    $this->defaultValue = empty($default) ? ($this->levels[1] ?? NULL) : $default;
  }

  /**
   * Helper method to calculate options.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param bool $isMonthly
   *   Flag to use monthly amounts.
   *
   * @return array<string, string>
   *   An array of options.
   */
  protected function calculateOptions(FormStateInterface $form_state, $isMonthly = FALSE): array {
    $options = [];
    /** @var array<string, mixed> $presetLevels */
    $presetLevels = is_array($this->getSetting('levels')) ? $this->getSetting('levels') : [];
    $key = $isMonthly ? 'monthly' : 'single';
    $levels = isset($presetLevels[$key]) && is_array($presetLevels[$key]) ? $presetLevels[$key] : [];
    foreach ($levels as $level_id => $level_amount) {
      if (empty($level_amount)) {
        // Skip the option if there is no amount.
        continue;
      }
      $amountStr = is_scalar($level_amount) || $level_amount instanceof \Stringable ? (string) $level_amount : '0';
      $options[(string) $level_id] = $this->formatAmount($amountStr, $isMonthly);
    }
    $options[$this->getCustomLevelId()] = 'Enter amount below:';
    return $options;
  }

  /**
   * Helper method to calculate default value.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array<string, mixed> $value
   *   The value array to use in calculation.
   * @param bool $isMonthly
   *   Flag to use monthly amounts.
   *
   * @return string|null
   *   Returns null if no default determined.
   */
  protected function calculateDefault(FormStateInterface $form_state, array $value, $isMonthly = FALSE): ?string {
    $default = NULL;
    $incomingNumber = isset($value['number']) && is_numeric($value['number']) ? (int) $value['number'] : NULL;
    /** @var array<string, mixed> $presetLevels */
    $presetLevels = is_array($this->getSetting('levels')) ? $this->getSetting('levels') : [];
    $key = $isMonthly ? 'monthly' : 'single';
    $levels = isset($presetLevels[$key]) && is_array($presetLevels[$key]) ? $presetLevels[$key] : [];
    foreach ($levels as $level_id => $level_amount) {
      if (empty($level_amount)) {
        // Skip the option if there is no amount.
        continue;
      }
      if (is_numeric($level_amount) && (int) $level_amount === $incomingNumber) {
        $default = (string) $level_id;
      }
    }
    if (empty($default) && !empty($incomingNumber)) {
      $default = $this->getCustomLevelId();
    }
    return $default;
  }

  /**
   * Utility function to extract the custom level id from settings.
   *
   * @return string
   *   The level id.
   */
  protected function getCustomLevelId(): string {
    return 'custom_amount';
  }

  /**
   * Helper method to format the amount.
   *
   * @param string $amount
   *   The amount to format.
   * @param bool $addMonthly
   *   Should the /mo. string be added?
   * @param bool $symbol
   *   If a currency symbol should be used.
   *
   * @return string
   *   The formatted output.
   */
  protected function formatAmount($amount, $addMonthly = FALSE, $symbol = TRUE): string {
    $formatOptions = [
      'currency_display' => 'symbol',
      'minimum_fraction_digits' => 0,
    ];
    if (!$symbol) {
      $formatOptions['currency_display'] = 'none';
    }
    $formatted = $this->currencyFormatter->format(
      $amount,
      $this->currencyCode(),
      $formatOptions
    );
    if ($addMonthly) {
      $formatted .= '/mo';
    }
    return $formatted;
  }

}
