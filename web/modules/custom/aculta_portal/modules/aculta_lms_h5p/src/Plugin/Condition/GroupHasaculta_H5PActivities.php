<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Plugin\Condition;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lms\Entity\Bundle\CourseInterface;

/**
 * CTools-compatible condition for course displays with interactive activities.
 */
#[Condition(
  id: 'aculta_lms_h5p_group_has_activities',
  label: new TranslatableMarkup('O curso tem atividades interativas (H5P)'),
  category: new TranslatableMarkup('LMS ACULTA'),
  context_definitions: [
    'group' => new EntityContextDefinition('entity:group', new TranslatableMarkup('Curso')),
  ],
)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
final class GroupHasaculta_H5PActivities extends ConditionPluginBase {

  /**
   * Evaluates from the Group field, which remains the LMS source of truth. */
  public function evaluate(): bool {
    $course = $this->getContextValue('group');
    return $course instanceof CourseInterface
      && $course->bundle() === 'lms_course'
      && $course->hasField('field_h5p_activities')
      && !$course->get('field_h5p_activities')->isEmpty();
  }

  /**
   * Summarizes the condition for displays that require H5P activities. */
  public function summary(): string {
    return (string) $this->t('O curso contém pelo menos uma atividade H5P.');
  }

  /**
   * Returns cache tags for the current course or the group list. */
  public function getCacheTags(): array {
    $course = $this->getContextValue('group');
    return $course instanceof CourseInterface ? Cache::mergeTags($course->getCacheTags(), ['aculta_h5p_content_list']) : ['group_list'];
  }

  /**
   * Returns no additional cache contexts for this condition. */
  public function getCacheContexts(): array {
    return [];
  }

  /**
   * Returns permanent cacheability for the course activity condition. */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

}
