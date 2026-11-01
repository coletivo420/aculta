<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Controller;

use Drupal\aculta_forum_lms\Access\LmsForumModerationManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Presents Group-scoped LMS discussion moderation queues.
 */
final class LmsForumModerationController {

  use StringTranslationTrait;

  private const VIEW_ID = 'aculta_forum_lms_admin_comments';

  /**
   * Route defaults use an allowlist of embedded display IDs.
   *
   * @var array<string, string>
   */
  private const DISPLAYS = [
    'published' => 'lms_published',
    'rejected' => 'lms_rejected',
    'pending' => 'lms_pending',
  ];

  public function __construct(
    private readonly LmsForumModerationManager $moderationManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ViewExecutableFactory $viewExecutableFactory,
    private readonly AccountProxyInterface $currentUser,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Builds the Educator's authorized course/class list.
   */
  public function overview(): array {
    [$groups, $cacheability] = $this->moderationManager->getModerationGroups($this->currentUser);
    $courses = [];
    foreach ($groups as $group) {
      $course = $group->bundle() === 'lms_course'
        ? $group
        : $this->entityTypeManager->getStorage('group')->load($this->moderationManager->courseIdForGroup($group));
      if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
        continue;
      }
      $courseId = (string) $course->id();
      $cacheability->addCacheableDependency($course);
      $courses[$courseId] ??= [
        'title' => $course->label(),
        'classes' => [],
      ];
      $courses[$courseId]['classes'][] = Link::fromTextAndUrl(
        $group->bundle() === 'lms_course' ? $this->t('Fórum do curso') : $group->label(),
        Url::fromRoute('aculta_forum_lms.comments_pending', [
          'course' => $courseId,
          'group' => $group->id(),
        ]),
      )->toRenderable();
    }
    $items = [];
    foreach ($courses as $course) {
      $items[] = [
        'course' => ['#plain_text' => $course['title']],
        'groups' => [
          '#theme' => 'item_list',
          '#items' => $course['classes'],
        ],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'lms-forum-moderation-overview'],
      'contexts' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Fóruns por curso'),
        '#items' => $items,
        '#empty' => $this->t('Você não tem cursos ou turmas com moderação de discussões.'),
      ],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Builds one fixed-status View, restricted to the route's Group ID.
   */
  public function comments(GroupInterface $group, GroupInterface $course, string $queue): array {
    $display = self::DISPLAYS[$queue] ?? NULL;
    if ($display === NULL) {
      throw new NotFoundHttpException();
    }
    if ((string) $this->moderationManager->courseIdForGroup($group) !== (string) $course->id()) {
      throw new NotFoundHttpException();
    }

    $config = $this->entityTypeManager->getStorage('view')->load(self::VIEW_ID);
    if ($config === NULL) {
      throw new NotFoundHttpException();
    }
    $view = $this->viewExecutableFactory->get($config);
    if (!$view->access($display)) {
      throw new AccessDeniedHttpException();
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'lms-forum-moderation-queue'],
      'view' => [
        '#type' => 'view',
        '#name' => self::VIEW_ID,
        '#display_id' => $display,
        '#arguments' => [(string) $group->id()],
      ],
    ];
    CacheableMetadata::createFromObject($group)->addCacheableDependency($course)->applyTo($build);
    return $build;
  }

}
