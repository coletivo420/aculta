<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Adds indexes spanning fields on the internal learning entity tables. */
final class LearningEntityStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * Acrescenta índices que abrangem campos das entidades de aprendizagem.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   Definição do tipo de entidade.
   * @param bool $reset
   *   Quando TRUE, descarta o esquema em cache.
   *
   * @return array
   *   Esquema da entidade, com os índices extras.
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $table = $entity_type->getBaseTable() ?? '';
    if (!isset($schema[$table])) {
      return $schema;
    }

    $required_columns = match ($entity_type->id()) {
      'aculta_lms_lesson' => ['course_id', 'title', 'published', 'weight', 'changed'],
      'aculta_lms_activity' => ['lesson_id', 'type', 'graded', 'weight', 'answer_config', 'changed'],
      'aculta_lms_answer' => ['activity_id', 'uid', 'state', 'created', 'changed'],
      'aculta_lms_progress' => ['course_id', 'uid', 'status', 'finished', 'changed'],
      default => [],
    };
    foreach ($required_columns as $column) {
      if (isset($schema[$table]['fields'][$column])) {
        $schema[$table]['fields'][$column]['not null'] = TRUE;
      }
    }
    if ($entity_type->id() === 'aculta_lms_answer') {
      $schema[$table]['fields']['state']['length'] = 16;
    }
    elseif ($entity_type->id() === 'aculta_lms_progress') {
      $schema[$table]['fields']['status']['length'] = 32;
    }

    if ($entity_type->id() === 'aculta_lms_progress') {
      $schema[$table]['unique keys']['course_uid'] = ['course_id', 'uid'];
    }
    elseif ($entity_type->id() === 'aculta_lms_lesson') {
      $schema[$table]['indexes']['course_weight'] = ['course_id', 'weight'];
    }
    elseif ($entity_type->id() === 'aculta_lms_activity') {
      $schema[$table]['indexes']['lesson_weight'] = ['lesson_id', 'weight'];
    }
    elseif ($entity_type->id() === 'aculta_lms_answer') {
      $schema[$table]['indexes']['activity_uid'] = ['activity_id', 'uid'];
    }
    return $schema;
  }

}
