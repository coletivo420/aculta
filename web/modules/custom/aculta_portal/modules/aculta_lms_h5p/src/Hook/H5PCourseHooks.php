<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;

/** Attaches the native H5P field to LMS courses without replacing Group forms. */
final class H5PCourseHooks {

  /** Shows H5P in the course page; field formatter performs access checks. */
  #[Hook('entity_view_display_alter')]
  public function courseViewDisplay(EntityViewDisplayInterface $display, array $context): void {
    if (($context['entity_type'] ?? NULL) !== 'group' || ($context['bundle'] ?? NULL) !== 'lms_course') {
      return;
    }
    $display->setComponent('field_h5p_activities', [
      'type' => 'h5p_default',
      'label' => 'hidden',
      'weight' => 25,
      'region' => 'content',
      'settings' => [],
      'third_party_settings' => [],
    ]);
  }

}
