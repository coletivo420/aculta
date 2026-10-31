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
 * Composes the LMS-owned Group add form in the Ganjalab presentation.
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
   * Builds the existing Group add form for the LMS course bundle.
   *
   * Route permission is checked before this controller runs. Entity access is
   * checked again here so the adapter cannot weaken the owner's create ACL.
   *
   * @return array<string, mixed>
   *   Owner Form API inside the shared Ganjalab presentation.
   */
  public function create(): array {
    $course = $this->entityTypeManager->getStorage('group')->create(['type' => 'lms_course']);
    if (!$course->access('create', $this->account, TRUE)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['studio', 'studio-course-authoring'],
        'data-aculta-studio' => 'course-authoring',
        'data-portal-ui' => 'studio-shell',
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site', 'route'],
        'max-age' => 0,
      ],
      'back' => PortalLinkElement::build(
        $this->t('Voltar ao ACULTA Studio Ganjalab'),
        Url::fromRoute('aculta_lms.student_dashboard'),
        [
          'class' => 'studio-course-authoring__back',
          'data-aculta-studio' => 'course-create-back',
          'data-portal-ui' => 'studio-action',
        ],
      ),
      'content' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['studio__main'],
          'data-aculta-studio' => 'course-form',
        ],
        'form' => $this->entityFormBuilder->getForm($course, 'add'),
      ],
    ];
  }

}
