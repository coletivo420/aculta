<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\UiPatterns\Source;

use Drupal\Core\Field\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewsSourceBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Exposes an access-checked entity field value from a Views row to a prop.
 */
#[Source(
  id: 'view_field_value',
  label: new TranslatableMarkup('[View row] Field value'),
  description: new TranslatableMarkup('Access-checked raw entity field value for a component prop.'),
  prop_types: ['boolean', 'number', 'string', 'unknown'],
  context_requirements: ['views:row'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view', label: new TranslatableMarkup('View')),
  ],
)]
final class ViewFieldValueSource extends ViewsSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $view = $this->getView();
    $field_id = (string) ($this->getSetting('ui_patterns_views_field') ?? '');
    $row_index = isset($this->context['ui_patterns_views:row:index'])
      ? (int) $this->getContextValue('ui_patterns_views:row:index')
      : 0;
    if (!$view instanceof ViewExecutable || $field_id === '' || !isset($view->field[$field_id], $view->result[$row_index])) {
      return NULL;
    }

    $field = $view->field[$field_id];
    if (!$field instanceof FieldPluginBase || $this->isViewFieldExcluded($field_id, $view)) {
      return NULL;
    }

    $row = $view->result[$row_index];
    $entity = $row->_entity ?? NULL;
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field_id)) {
      return NULL;
    }
    $this->addCacheableDependency($entity);
    $access = $entity->get($field_id)->access('view', $this->currentUser, TRUE);
    $this->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return NULL;
    }

    $value = $field->getValue($row);
    if ($value === NULL || $value === '') {
      return NULL;
    }

    $type = $this->getPropDefinition()['type'] ?? NULL;
    $types = \is_array($type) ? $type : [$type];
    if (\in_array('boolean', $types, TRUE)) {
      return (bool) $value;
    }
    if (\in_array('integer', $types, TRUE) && \is_numeric($value)) {
      return (int) $value;
    }
    if (\in_array('number', $types, TRUE) && \is_numeric($value)) {
      return \str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $options = self::getViewsFieldOptions($this->getView());
    if (!\is_array($options) || $options === []) {
      return $form;
    }
    $form['ui_patterns_views_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field'),
      '#description' => $this->t('Select the Views field whose raw value supplies this prop.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('ui_patterns_views_field') ?? '',
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $field_id = (string) ($this->getSetting('ui_patterns_views_field') ?? '');
    if ($field_id === '') {
      return [];
    }
    $options = self::getViewsFieldOptions($this->getView());
    return [(string) ($options[$field_id] ?? $field_id)];
  }

}
