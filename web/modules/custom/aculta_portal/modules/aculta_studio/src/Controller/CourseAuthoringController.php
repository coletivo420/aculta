<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Composes the LMS-owned Group add form in the Ganjalab Educador workspace.
 *
 * The Group entity, access rules, fields, validation, persistence and submit
 * behavior remain with the Group/LMS owners. This controller only chooses the
 * existing Form API operation and places it in the ACULTA Studio shell.
 */
final class CourseAuthoringController {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFormBuilderInterface $entityFormBuilder,
    private readonly AccountInterface $account,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Lists courses this educator can update through Group entity access.
   */
  public function dashboard(): array {
    if (!$this->account->hasPermission('create lms_course group')) {
      throw new AccessDeniedHttpException();
    }
    $storage = $this->entityTypeManager->getStorage('group');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'lms_course')->sort('label')->execute();
    $items = [];
    foreach ($storage->loadMultiple($ids) as $course) {
      $access = $course->access('update', $this->account, TRUE);
      if (!$access->isAllowed()) {
        continue;
      }
      $items[] = [
        '#type' => 'link',
        '#title' => $course->label(),
        '#url' => $course->toUrl(),
        '#cache' => ['contexts' => ['user', 'user.permissions']],
      ];
    }
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'data-aculta-studio' => 'course-educator-dashboard',
        'data-portal-ui' => 'studio-course-educator',
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Ganjalab Educador'),
        '#weight' => -100,
      ],
      'intro' => [
        '#plain_text' => $this->t('Crie e acompanhe cursos conforme suas permissões de educador.'),
        '#weight' => -90,
      ],
      'create' => PortalLinkElement::build(
        $this->t('Novo curso'),
        Url::fromRoute('aculta_lms.course_create'),
        [
          'data-aculta-studio' => 'course-create',
          'data-portal-ui' => 'button-primary',
        ],
      ),
      'courses' => ['#weight' => 0] + ($items ?: [
        '#plain_text' => $this->t('Nenhum curso disponível para gestão.'),
      ]),
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];

    $build['create']['#weight'] = -80;

    $studentUrl = Url::fromRoute('aculta_lms.student_dashboard');
    if ($studentUrl->access($this->account)) {
      $build['student_workspace'] = PortalLinkElement::build(
        $this->t('Ir para Ganjalab Estudante'),
        $studentUrl,
        [
          'data-aculta-studio' => 'educator-to-student',
          'data-portal-ui' => 'studio-action',
        ],
      );
      $build['student_workspace']['#weight'] = -70;
    }

    return $build;
  }

  /**
   * Builds the existing Group add form for the LMS course bundle.
   *
   * Route permission is checked before this controller runs. Entity access is
   * checked again here so the adapter cannot weaken the owner's create ACL.
   *
   * @return array<string, mixed>
   *   Owner Form API inside the Ganjalab Educador workspace.
   */
  public function create(): array {
    $course = $this->entityTypeManager->getStorage('group')->create(['type' => 'lms_course']);
    if (!$course->access('create', $this->account, TRUE)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'data-aculta-studio' => 'course-authoring',
        'data-portal-ui' => 'studio-shell studio-course-authoring',
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site', 'route'],
        'max-age' => 0,
      ],
      'back' => PortalLinkElement::build(
        $this->t('Voltar ao Ganjalab Educador'),
        Url::fromRoute('aculta_lms.educator_dashboard'),
        [
          'data-aculta-studio' => 'course-create-back',
          'data-portal-ui' => 'studio-action studio-course-authoring-back',
        ],
      ),
      'content' => [
        '#type' => 'container',
        '#attributes' => [
          'data-aculta-studio' => 'course-form',
          'data-portal-ui' => 'studio-main',
        ],
        'form' => $this->entityFormBuilder->getForm($course, 'add'),
      ],
    ];
  }

}
