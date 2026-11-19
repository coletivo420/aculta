<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Resources\CourseResourceBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds course materials to the existing course overview. */
final class CourseResourcesHooks {

  public function __construct(
    #[Autowire(service: 'aculta_lms_resources.course_resource_builder')]
    private readonly CourseResourceBuilder $builder,
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
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
    if (!$entity instanceof GroupInterface || $viewMode !== 'full'
      || !$display->getComponent('aculta_lms_course_resources')) {
      return;
    }
    $build['aculta_lms_course_resources'] = $this->builder->build($entity);
  }

  /**
   * Adds existing course resources to the educator's structure workspace.
   *
   * The Group edit form remains the canonical editor for the existing
   * reference fields; this hook only composes its current safe resource list
   * into the LMS-owned structure page.
   */
  #[Hook('preprocess_page')]
  public function addResourcesToCourseBuilder(array &$variables): void {
    if ($this->routeMatch->getRouteName() !== 'aculta_lms.course_structure'
      || !isset($variables['page']['content'])
      || !is_array($variables['page']['content'])) {
      return;
    }

    $course = $this->routeMatch->getParameter('course');
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return;
    }

    $resources = $this->builder->build($course);
    $resources['edit'] = Link::fromTextAndUrl(
      new TranslatableMarkup('Gerenciar materiais do curso'),
      Url::fromRoute('aculta_lms.course_materials_edit', ['course' => $course->id()]),
    )->toRenderable();
    $resources['#weight'] = 30;
    $resources['#attributes']['class'][] = 'aculta-lms-course-builder__resources';
    $variables['page']['content']['aculta_lms_course_builder_resources'] = $resources;
  }

}
