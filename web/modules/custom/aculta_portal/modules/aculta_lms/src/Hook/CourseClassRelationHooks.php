<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeInterface;
use Drupal\aculta_lms\Plugin\Group\Relation\CourseClassRelation;

/**
 * Selects the ACULTA implementation for the stable class relationship ID.
 */
final class CourseClassRelationHooks {

  /**
   * Provides the permission used by course and class administration.
   */
  #[Hook('permission')]
  public function permissions(): array {
    return [
      'administer lms' => [
        'title' => new TranslatableMarkup('Administrar cursos e turmas'),
        'restrict access' => TRUE,
      ],
    ];
  }

  /**
   * Overrides the contrib definition while preserving its stable ID.
   */
  #[Hook('group_relation_type_alter')]
  public function groupRelationTypeAlter(array &$definitions): void {
    if (($definitions['lms_classes'] ?? NULL) instanceof GroupRelationTypeInterface) {
      $definitions['lms_classes']->setClass(CourseClassRelation::class);
    }
  }

}
