<?php

namespace Drupal\aculta_donation\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
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
   * @var \Drupal\commerce_store\CurrentStore
   */
  protected $store;

  /**
   * Injected service.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * Level keys.
   *
   * @var array
   */
  protected $levels;

  /**
   * The current radio options.
   *
   * @var array
   */
  protected $options;

  /**
   * The current default value.
   *
   * @var array
   */
  protected $defaultValue;

  // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found

  /**
   * {@inheritdoc}
   */
  final public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings) {
    // @see https://phpstan.org/blog/solving-phpstan-error-unsafe-usage-of-new-static
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings']
    );
    $instance->store = $container->get('commerce_store.current_store');
    $instance->currencyFormatter = $container->get('commerce_price.currency_formatter');
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
   */
  public static function defaultSettings() {
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
    $currency = (string) $this->getSetting('currency');
    return $currency !== '' ? $currency : $this->store->getStore()->getDefaultCurrencyCode();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $levels = $this->getSetting('levels');
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
      foreach ($this->levels as $level) {
        if ($level == 'custom_amount') {
          continue;
        }
        $form['levels'][$type][$level] = [
          '#type' => 'number',
          '#title' => $this
            ->t('Amount (@level):', ['@level' => $level]),
          '#default_value' => $levels[$type][$level] ?? 5,
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
  protected function filterEmptyAmount($value) {
    return !empty($value);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $class = get_class($this);
    $summary = parent::settingsSummary();
    $levels = $this->getSetting('levels');
    $singleValues = isset($levels["single"]) ? implode(', ', array_filter($levels["single"], [
      $class,
      'filterEmptyAmount',
    ])) : '';
    $monthlyValues = isset($levels["monthly"]) ? implode(', ', array_filter($levels["monthly"], [
      $class,
      'filterEmptyAmount',
    ])) : '';
    $summary[] = $this->t('Single: @values', ['@values' => $singleValues]);
    $summary[] = $this->t('Monthly: @values', ['@values' => $monthlyValues]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ) {
    $this->init($items, $delta, $element, $form, $form_state);
    $customLevelId = $this->getCustomLevelId();
    $element = [
      'donation_level' => [
        '#type' => 'fieldset',
        '#title' => $this
          ->t('Amount'),
        '#required' => $element['#required'],
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
      $incomingValue = $items->first()->getValue();
      $useCustom = $this->defaultValue == $customLevelId;
      $element['donation_level']['amount'] = [
        '#type' => 'number',
        '#title' => $label,
        '#placeholder' => $label,
        '#title_display' => 'before',
        '#step' => 'any',
        '#min' => 5,
        '#default_value' => $useCustom && isset($incomingValue['number']) ? $this->formatAmount($incomingValue['number'], FALSE, FALSE) : NULL,
        '#attributes' => [
          'data-donation-level' => 'custom-amount',
        ],
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(
    array $values,
    array $form,
    FormStateInterface $form_state,
  ) {
    $customLevelId = $this->getCustomLevelId();
    $gift_type = reset($form_state->getValue([
      'commerce_donation_pane',
      'field_gift_type',
    ]));
    $isMonthly = reset($gift_type) == 'recurring';
    $options = $this->calculateOptions($form_state, $isMonthly);
    foreach ($values as &$item) {
      if ($item['donation_level']['value'] == $customLevelId) {
        // A custom amount is used.
        // Force empty amount to 0.
        if ($item['donation_level']['amount'] === '') {
          $item['donation_level']['amount'] = 0;
        }
        // Add currency code.
        $value = [
          'number' => $item['donation_level']['amount'],
          'currency_code' => $this->currencyCode(),
        ];
      }
      else {
        // Get amount from the selected option.
        $value = [
          'number' => preg_replace('/\D/', '', $options[$item['donation_level']['value']]),
          'currency_code' => $this->currencyCode(),
        ];
      }
      $item = $value;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(
    array $element,
    ConstraintViolationInterface $error,
    array $form,
    FormStateInterface $form_state,
  ) {
    $error_element = NestedArray::getValue(
      $element['donation_level'],
      $error->arrayPropertyPath
    );
    return is_array($error_element) ? $error_element : FALSE;
  }

  /**
   * Utility function to build the options array and default value for the form.
   */
  protected function init(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $items->getEntity();
    $isMonthly = FALSE;
    $incomingValue = $items->first()->getValue();
    $userInput = $form_state->getUserInput();
    if (empty($userInput) && $entity->hasField('field_gift_type') && !$entity->field_gift_type->isEmpty()) {
      $isMonthly = $entity->field_gift_type->first()->value == 'recurring';
    }
    elseif (!empty($userInput)) {
      // Clear any set values as we are changing the options.
      $incomingValue = [];
      $userInput["commerce_donation_pane"]["field_donation_amount"][0]["donation_level"] = NULL;
      $form_state->setUserInput($userInput);
      // Check if now Monthly is selected.
      if (isset($userInput["commerce_donation_pane"]["field_gift_type"])) {
        $isMonthly = $userInput["commerce_donation_pane"]["field_gift_type"] == 'recurring';
      }
    }
    $this->options = $this->calculateOptions($form_state, $isMonthly);
    $default = $this->calculateDefault($form_state, $incomingValue, $isMonthly);
    $this->defaultValue = empty($default) ? $this->levels[1] : $default;
  }

  /**
   * Helper method to calculate options.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param bool $isMonthly
   *   Flag to use monthly amounts.
   *
   * @return array
   *   An array of options.
   */
  protected function calculateOptions(FormStateInterface $form_state, $isMonthly = FALSE) {
    $options = [];
    $presetLevels = $this->getSetting('levels');
    $levels = $isMonthly ? $presetLevels['monthly'] : $presetLevels['single'];
    foreach ($levels as $level_id => $level_amount) {
      if (empty($level_amount)) {
        // Skip the option if there is no amount.
        continue;
      }
      $options[$level_id] = $this->formatAmount($level_amount, $isMonthly);
    }
    $options[$this->getCustomLevelId()] = 'Enter amount below:';
    return $options;
  }

  /**
   * Helper method to calculate default value.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $value
   *   The value to array to use in calculation.
   * @param bool $isMonthly
   *   Flag to use monthly amounts.
   *
   * @return null|string
   *   Returns null if no default determined.
   */
  protected function calculateDefault(FormStateInterface $form_state, array $value, $isMonthly = FALSE) {
    $default = NULL;
    $incomingNumber = isset($value['number']) ? (int) $value['number'] : NULL;
    $presetLevels = $this->getSetting('levels');
    $levels = $isMonthly ? $presetLevels['monthly'] : $presetLevels['single'];
    foreach ($levels as $level_id => $level_amount) {
      if (empty($level_amount)) {
        // Skip the option if there is no amount.
        continue;
      }
      if ($level_amount == $incomingNumber) {
        $default = $level_id;
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
  protected function getCustomLevelId() {
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
  protected function formatAmount($amount, $addMonthly = FALSE, $symbol = TRUE) {
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
