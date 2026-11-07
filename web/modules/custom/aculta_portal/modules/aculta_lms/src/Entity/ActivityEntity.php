<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\aculta_lms\Learning\CourseActivity;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Define a entidade de atividade de uma aula do LMS.
 */
#[ContentEntityType(
  id: 'aculta_lms_activity',
  label: new TranslatableMarkup('Atividade'),
  base_table: 'aculta_lms_activity',
  entity_keys: ['id' => 'id', 'uuid' => 'uuid'],
  handlers: [
    'access' => \Drupal\aculta_lms\Access\InternalLearningEntityAccessControlHandler::class,
    'storage_schema' => LearningEntityStorageSchema::class,
  ],
  internal: TRUE,
)]
final class ActivityEntity extends ContentEntityBase {

  /**
   * Define os campos base da atividade.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Definição do tipo de entidade.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Campos base indexados pelo nome.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['lesson_id'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('Aula'))->setRequired(TRUE);
    $fields['type'] = BaseFieldDefinition::create('string')->setLabel(new TranslatableMarkup('Tipo'))->setSetting('max_length', 64)->addConstraint('Regex', ['pattern' => '/^[a-z_]+$/'])->setRequired(TRUE);
    $fields['graded'] = BaseFieldDefinition::create('boolean')->setLabel(new TranslatableMarkup('Incluída na nota'))->setDefaultValue(FALSE)->setRequired(TRUE);
    $fields['weight'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('Ordem'))->addConstraint('Range', ['min' => 0])->setRequired(TRUE)->setDefaultValue(0);
    $fields['answer_config'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Configuração da resposta'))
      ->setRequired(TRUE)
      ->setDefaultValue('{}');
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(new TranslatableMarkup('Alterada em'));
    return $fields;
  }

  /**
   * Valida as regras da atividade antes de salvar.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Armazenamento da entidade.
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    self::assertActivityRules((string) $this->get('type')->value, (int) $this->get('lesson_id')->value, (bool) $this->get('graded')->value, (int) $this->get('weight')->value);
  }

  /**
   * Applies the activity contract and its non-negative ordering constraint. */
  public static function assertActivityRules(string $type, int $lesson_id, bool $graded, int $weight): void {
    new CourseActivity(1, $lesson_id, $type, $graded);
    if ($weight < 0) {
      throw new \InvalidArgumentException('Ordem da atividade não pode ser negativa.');
    }
  }

}
