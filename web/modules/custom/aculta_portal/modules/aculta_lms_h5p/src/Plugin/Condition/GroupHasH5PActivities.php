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
  label: new TranslatableMarkup('Course has interactive activities (H5P)'),
  category: new TranslatableMarkup('ACULTA LMS'),
  context_definitions: [
    'group' => new EntityContextDefinition('entity:group', new TranslatableMarkup('Course')),
  ],
)]
final class GroupHasH5PActivities extends ConditionPluginBase {

  /** Evaluates from the Group field, which remains the LMS source of truth. */
  public function evaluate(): bool {
    $course = $this->getContextValue('group');
    return $course instanceof CourseInterface
      && $course->bundle() === 'lms_course'
      && $course->hasField('field_h5p_activities')
      && !$course->get('field_h5p_activities')->isEmpty();
  }

  public function summary(): string {
    return (string) $this->t('The course contains at least one H5P activity.');
  }

  public function getCacheTags(): array {
    $course = $this->getContextValue('group');
    return $course instanceof CourseInterface ? Cache::mergeTags($course->getCacheTags(), ['h5p_content_list']) : ['group_list'];
  }

  public function getCacheContexts(): array {
    return [];
  }

  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

}
