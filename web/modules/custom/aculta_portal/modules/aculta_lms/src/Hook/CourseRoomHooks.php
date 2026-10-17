<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\CourseRoomBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Adds the course overview to the existing Group canonical page. */
final class CourseRoomHooks {

  public function __construct(
    #[Autowire(service: 'aculta_lms.course_room_builder')]
    private readonly CourseRoomBuilder $courseRoomBuilder,
  ) {}

  /**
   * Declares the course room as a configurable extra field.
   *
   * @return array<string,mixed>
   *   Extra field definitions.
   */
  #[Hook('entity_extra_field_info')]
  public function extraFieldInfo(): array {
    return [
      'group' => [
        'lms_course' => [
          'display' => [
            'aculta_lms_course_room' => [
              'label' => 'Sala do curso',
              'description' => 'Índice de lições e acesso à sala nativa do Drupal LMS.',
              'weight' => 20,
              'visible' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Adds the overview only when its extra field is enabled in the display.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $viewMode): void {
    if (!$entity instanceof CourseInterface || $viewMode !== 'full' || !$display->getComponent('aculta_lms_course_room')) {
      return;
    }
    $build['aculta_lms_course_room'] = $this->courseRoomBuilder->build($entity);
  }

}
