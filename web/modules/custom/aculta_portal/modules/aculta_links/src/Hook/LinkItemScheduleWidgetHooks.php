<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Hook;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Presents item scheduling times in the site's configured timezone. */
final class LinkItemScheduleWidgetHooks {

  public function __construct(
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Sets the DateTime widget timezone for ACULTA Links schedule fields.
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $field_definition = $context['items']->getFieldDefinition();
    if (!$field_definition instanceof FieldDefinitionInterface
      || $field_definition->getTargetEntityTypeId() !== 'aculta_link_item'
      || !in_array($field_definition->getName(), ['available_from', 'available_until'], TRUE)
      || $context['widget']->getPluginId() !== 'datetime_default'
      || !isset($element['value']['#date_timezone'])) {
      return;
    }

    $timezone = $this->configFactory->get('system.date')->get('timezone.default');
    $timezone = is_string($timezone) && $timezone !== '' ? $timezone : DateTimeItemInterface::STORAGE_TIMEZONE;
    try {
      $date_timezone = new \DateTimeZone($timezone);
    }
    catch (\Exception) {
      $date_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $timezone = DateTimeItemInterface::STORAGE_TIMEZONE;
    }

    $element['value']['#date_timezone'] = $timezone;
    $default_value = $element['value']['#default_value'] ?? NULL;
    if ($default_value instanceof DrupalDateTime) {
      $default_value->setTimezone($date_timezone);
    }
  }

}
