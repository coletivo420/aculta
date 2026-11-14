<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds the Core entity form for one exact Forum post revision.
 */
final class ForumPostModerationFormController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $forumEntityTypeManager,
    private readonly EntityFormBuilderInterface $forumEntityFormBuilder,
  ) {}

  /**
   * Returns the moderation form for the route's revision identifier.
   */
  public function form(string $revision_id): array {
    $storage = $this->forumEntityTypeManager->getStorage('aculta_forum_post');
    if (!$storage instanceof RevisionableStorageInterface) {
      throw new NotFoundHttpException();
    }
    $post = $storage->loadRevision($revision_id);
    if ($post === NULL) {
      throw new NotFoundHttpException();
    }

    return $this->forumEntityFormBuilder->getForm($post, 'default');
  }

}
