<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\Group\Relation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Plugin\Attribute\GroupRelationType;
use Drupal\group\Plugin\Group\Relation\GroupRelationBase;

/**
 * Relates one class to an activity for a class-specific deadline. */
#[GroupRelationType(
  id: 'lms_class_activity_due',
  entity_type_id: 'lms_activity',
  label: new TranslatableMarkup('Prazo de atividade da turma'),
  description: new TranslatableMarkup('Define o prazo de uma atividade em uma turma.'),
  reference_label: new TranslatableMarkup('Atividade'),
  reference_description: new TranslatableMarkup('Atividade que recebe prazo nesta turma.'),
  admin_permission: 'administer lms',
  pretty_path_key: 'atividade',
  enforced: FALSE,
)]
final class ClassActivityDueDate extends GroupRelationBase {

  /**
   * {@inheritdoc} */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['entity_cardinality'] = 1;
    return $configuration;
  }

  /**
   * {@inheritdoc} */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['entity_cardinality']['#disabled'] = TRUE;
    return $form;
  }

}
