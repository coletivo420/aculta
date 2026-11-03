<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\Group\Relation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\Attribute\GroupRelationType;
use Drupal\group\Plugin\Group\Relation\GroupRelationBase;

/**
 * Relates course Groups to class Groups without the LMS classes submodule.
 */
#[GroupRelationType(
  id: 'lms_classes',
  entity_type_id: 'group',
  label: new TranslatableMarkup('Turma do curso'),
  description: new TranslatableMarkup('Relaciona turmas a cursos.'),
  reference_label: new TranslatableMarkup('Turma'),
  reference_description: new TranslatableMarkup('Turma relacionada ao curso.'),
  entity_bundle: 'lms_class',
  admin_permission: 'administer lms',
  pretty_path_key: 'class',
  enforced: FALSE,
)]
final class CourseClassRelation extends GroupRelationBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = parent::defaultConfiguration();
    $configuration['group_cardinality'] = 0;
    $configuration['entity_cardinality'] = 1;
    return $configuration;
  }

  /**
   * {@inheritdoc} */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $info = $this->t('A cardinalidade é fixa para preservar a relação entre curso e turma.');
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['group_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';
    $form['group_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';
    return $form;
  }

}
