<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\aculta_lms\Learning\CourseLesson;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Stores course lessons as ACULTA-owned content entities.
 */
#[ContentEntityType(
  id: 'aculta_lms_lesson',
  label: new TranslatableMarkup('Aula'),
  base_table: 'aculta_lms_lesson',
  entity_keys: ['id' => 'id', 'uuid' => 'uuid'],
  handlers: [
    'access' => \Drupal\aculta_lms\Access\InternalLearningEntityAccessControlHandler::class,
    'storage_schema' => LearningEntityStorageSchema::class,
  ],
  internal: TRUE,
)]
final class LessonEntity extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['course_id'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('Curso (referência opaca)'))->setRequired(TRUE);
    $fields['title'] = BaseFieldDefinition::create('string')->setLabel(new TranslatableMarkup('Título'))->setSetting('max_length', 255)->setRequired(TRUE);
    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Conteúdo da aula'))
      ->setDefaultValue(['value' => '', 'format' => 'plain_text']);
    $fields['published'] = BaseFieldDefinition::create('boolean')->setLabel(new TranslatableMarkup('Publicada'))->setDefaultValue(FALSE)->setRequired(TRUE);
    $fields['weight'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('Ordem'))->addConstraint('Range', ['min' => 0])->setRequired(TRUE)->setDefaultValue(0);
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(new TranslatableMarkup('Alterada em'));
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    self::assertLessonRules((int) $this->get('course_id')->value, (string) $this->get('title')->value, (bool) $this->get('published')->value, (int) $this->get('weight')->value);
  }

  /**
   * Applies the lesson contract, including its non-negative ordering rule.
   *
   * @param int $course_id
   *   The owning Group ID.
   * @param string $title
   *   The lesson title.
   * @param bool $published
   *   Whether the lesson is available to students.
   * @param int $weight
   *   The non-negative position within the course.
   */
  public static function assertLessonRules(int $course_id, string $title, bool $published, int $weight): void {
    new CourseLesson(1, $course_id, $title, $published, $weight);
  }

}
