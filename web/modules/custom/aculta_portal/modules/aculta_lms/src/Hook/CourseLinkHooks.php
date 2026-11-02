<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\TrainingManager;

/**
 * Provides the learner-facing course action in Portuguese.
 *
 * Reuses the LMS start-link component and its canonical lms.course.start
 * destination instead of adding a second course action.
 */
final class CourseLinkHooks {

  public function __construct(
    private readonly TrainingManager $trainingManager,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Replaces the default English action for enrolled learners.
   *
   * @return array<string, mixed>
   *   Start-link component or an empty array to defer to the LMS default.
   */
  #[Hook('lms_course_link')]
  public function courseLink(CourseInterface $course, AccountInterface $account, CacheableMetadata $cacheability): array {
    if (!$course->isPublished()) {
      // The LMS block owns the "coming soon" presentation.
      return [];
    }
    $take_access = $course->access('take', $account, TRUE);
    $cacheability->addCacheableDependency($take_access);
    if (!$take_access->isAllowed()) {
      // Enrolment, login and purchase flows remain owned by the LMS block.
      return [];
    }
    if ($course->get(CourseInterface::LESSONS)->isEmpty()) {
      return $this->buildActionInfo('no-lessons', (string) $this->translation->translate('Este curso ainda não tem aulas publicadas.'));
    }

    $status = $this->trainingManager->loadCourseStatus($course, $account, ['current' => TRUE]);
    if ($status !== NULL) {
      $cacheability->addCacheableDependency($status);
    }
    $state = $status?->getStatus();
    $label = NULL;
    $action_info = NULL;
    if ($state === NULL || $state === CourseStatusInterface::STATUS_NEW) {
      $label = $this->translation->translate('Começar curso');
    }
    elseif ($state === CourseStatusInterface::STATUS_PROGRESS) {
      $label = $this->translation->translate('Continuar curso');
    }
    elseif ($state === CourseStatusInterface::STATUS_NEEDS_EVALUATION) {
      if ($course->ungradedAccess()) {
        $label = $this->translation->translate('Continuar curso (sem nota)');
      }
      else {
        $action_info = $this->buildActionInfo('needs-evaluation', (string) $this->translation->translate('Aguardando avaliação'));
      }
    }
    elseif ($state === CourseStatusInterface::STATUS_PASSED || $state === CourseStatusInterface::STATUS_FAILED) {
      $label = $course->revisitMode()
        ? $this->translation->translate('Revisitar curso')
        : $this->translation->translate('Reiniciar curso');
    }

    $link = NULL;
    if ($label !== NULL) {
      $url = Url::fromRoute('lms.course.start', ['group' => $course->id()], [
        'attributes' => ['data-portal-ui' => 'lms-start-link'],
      ]);
      $link = Link::fromTextAndUrl($label, $url)->toRenderable();
    }

    return [
      '#type' => 'component',
      '#component' => 'lms:start_link',
      '#props' => [
        'link' => $link,
        'action_info' => $action_info,
      ],
    ];
  }

  /**
   * Builds the LMS action-info component with a localized message.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildActionInfo(string $class, string $message): array {
    return [
      '#type' => 'component',
      '#component' => 'lms:course_action_info',
      '#props' => [
        'class' => $class,
        'info_text' => $message,
      ],
    ];
  }

}
