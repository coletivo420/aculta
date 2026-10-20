<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Controller;

use Drupal\aculta_forum_lms\Access\LmsForumModerationManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Presents Group-scoped LMS discussion moderation queues.
 */
final class LmsForumModerationController extends ControllerBase {

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
  ) {}

  /**
   * Builds the Educator's authorized course/class list.
   */
  public function overview(): array {
    [$groups, $cacheability] = $this->moderationManager->getModerationGroups($this->currentUser);
    $items = [];
    foreach ($groups as $group) {
      $items[] = Link::fromTextAndUrl(
        $group->label(),
        Url::fromRoute('aculta_forum_lms.comments_pending', ['group' => $group->id()]),
      )->toRenderable();
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'lms-forum-moderation-overview'],
      'contexts' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Cursos e turmas sob sua responsabilidade'),
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
  public function comments(GroupInterface $group, string $queue): array {
    $display = self::DISPLAYS[$queue] ?? NULL;
    if ($display === NULL) {
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
    CacheableMetadata::createFromObject($group)->applyTo($build);
    return $build;
  }

}
