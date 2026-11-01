<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources\Hook;

use Drupal\aculta_lms_resources\CourseResourceBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds course materials to the existing course overview. */
final class CourseResourcesHooks {

  public function __construct(
    #[Autowire(service: 'aculta_lms_resources.course_resource_builder')]
    private readonly CourseResourceBuilder $builder,
  ) {}

  /**
   * Declares the configurable course resources extra field.
   *
   * @return array<string, mixed>
   *   Extra field definition.
   */
  #[Hook('entity_extra_field_info')]
  public function extraFieldInfo(): array {
    return [
      'group' => [
        'lms_course' => [
          'display' => [
            'aculta_lms_course_resources' => [
              'label' => 'Materiais de apoio',
              'description' => 'Publicações da Revista, verbetes Wiki e galerias vinculados a este curso.',
              'weight' => 21,
              'visible' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Renders the resource list only in the configured full course display.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $viewMode): void {
    if (!$entity instanceof CourseInterface || $viewMode !== 'full'
      || !$display->getComponent('aculta_lms_course_resources')) {
      return;
    }
    $build['aculta_lms_course_resources'] = $this->builder->build($entity);
  }

}
