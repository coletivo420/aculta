<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Plugin\Field;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\Entity\aculta_H5PContent;

abstract class aculta_H5PWidgetBase extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Prevent setting default value
    if ($this->isDefaultValueWidget($form_state)) {
      $element += [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Ainda não há suporte para este campo.') . '</p>',
      ];
      return ['h5p_content' => $element];
    }

    $element += [
      '#type' => 'fieldset',
    ];

    $h5p_content_id = $items[$delta]->h5p_content_id ?? $items[$delta]->h5p_content['id'] ?? NULL;
    $element['id'] = [
      '#type' => 'value',
      '#value' => $h5p_content_id,
    ];

    // Make it possible to clear a field
    $element['clear_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limpar atividade'),
      '#description' => $this->t('Atenção: a atividade será excluída permanentemente.'),
      '#default_value' => 0,
      '#weight' => 40,
    ];

     $field_name = $items->getName();
     if (isset($element['#field_parents'])) {
       $identifier = implode('_', array_merge($element['#field_parents'], [$field_name, $delta]));
     }
     else {
       $identifier = implode('_', [$field_name, $delta]);
     }
     // Add path to this element.
     $element['element_path'] = [
       '#type' => 'value',
       '#value' => $identifier,
     ];

    // Determine if this is a new translation or not
    $element['new_translation'] = [
      '#type' => 'value',
      '#value' => !empty($form_state->get('content_translation')),
    ];

    // Load content
    $disable = NULL;
    $h5p_content = $h5p_content_id ? aculta_H5PContent::load($h5p_content_id) : NULL;
    if (!empty($h5p_content)) {
      $disable = $h5p_content->get('disabled_features')->value;
    }

    // Add display options
    $core = aculta_H5PDrupal::getInstance('core');
    $display_options = $core->getDisplayOptionsForEdit($disable === \H5PCore::DISABLE_NONE ? NULL : $disable);
    $labels = [
      \H5PCore::DISPLAY_OPTION_FRAME => $this->t('Exibir botões de download, incorporação e direitos autorais'),
      \H5PCore::DISPLAY_OPTION_DOWNLOAD => $this->t('Permitir que estudantes baixem a atividade'),
      \H5PCore::DISPLAY_OPTION_EMBED => $this->t('Botão de incorporação'),
      \H5PCore::DISPLAY_OPTION_COPYRIGHT => $this->t('Botão de direitos autorais'),
      \H5PCore::DISPLAY_OPTION_ABOUT => $this->t('Botão Sobre o H5P')
    ];
    foreach ($display_options as $name => $value) {
      $element[$name] = [
        '#type' => 'checkbox',
        '#title' => $labels[$name],
        '#default_value' => $value,
        '#weight' => 40,
        '#attributes' => [
          'id' => str_replace('_', '-', $field_name) . "-{$delta}-h5p-content-{$name}",
        ],
      ];

      if ($name !== \H5PCore::DISPLAY_OPTION_FRAME) {
        $element[$name]['#states'] = [
          'visible' => [
            '#' . str_replace('_', '-', $field_name) . "-{$delta}-h5p-content-" . \H5PCore::DISPLAY_OPTION_FRAME => ['checked' => TRUE],
          ]
        ];
      }

      if ($name === \H5PCore::DISPLAY_OPTION_DOWNLOAD) {
        $element[$name]['#description'] = $this->t('Quando selecionado, o botão de reutilização permite baixar esta atividade como arquivo .h5p.');
      }
    }

    return ['h5p_content' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    // We only message after validation has completed
    if (!$form_state->isValidationComplete()) {
      return $values;
    }

    // Determine if new revisions should be made
    $do_new_revision = self::doNewRevision($form_state);

    $return_values = [];
    foreach ($values as $delta => $value) {
      if (!isset($value['h5p_content'])) {
        continue; // Prevent crashing when there's no data
      }

      // Massage out each H5P Upload from the submitted form
      $return_values[$delta] = $this->massageFormValue($value['h5p_content'], $delta, $do_new_revision);
    }

    return $return_values;
  }

  /**
   * Help message out each value from the submitted form
   *
   * @param array $value
   * @param integer $delta
   * @param boolean $do_new_revision
   */
  protected function massageFormValue(array $value, $delta, $do_new_revision) {
    return [];
  }

  /**
   * Determine if the current entity is creating a new revision.
   * This is useful to avoid changing the H5P content belonging to
   * an older revision of the entity.
   *
   * @param FormStateInterface $form_state
   * @return boolean
   */
  private static function doNewRevision(FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if (!($form_object instanceof EntityFormInterface)) {
      // Not an entity form, nothing to alter.
      return FALSE;
    }
    $entity = $form_object->getEntity();

    // Determine if this is a new revision
    $is_new_revision = ($entity->getEntityType()->hasKey('revision') && $form_state->getValue('revision'));

    // Determine if we do revisioning for H5P content
    // (may be disabled to save disk space)
    $interface = aculta_H5PDrupal::getInstance();
    return $interface->getOption('revisioning', TRUE) && $is_new_revision;
  }

}
