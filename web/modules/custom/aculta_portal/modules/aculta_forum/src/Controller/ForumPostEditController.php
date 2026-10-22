<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Access\ForumPostPublicEditAccess;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds the public form for editing an author's published Forum post.
 */
final class ForumPostEditController extends ControllerBase {

  public function __construct(
    private readonly EntityFormBuilderInterface $entityFormBuilder,
    private readonly ForumPostPublicEditAccess $editAccess,
  ) {}

  /**
   * Returns the public form mode for the requested post.
   */
  public function edit(): array {
    $post = $this->editAccess->getPost();
    if ($post === NULL || !$post->access('update', $this->currentUser())->isAllowed()) {
      throw new NotFoundHttpException();
    }

    return $this->entityFormBuilder->getForm($post, 'public_submission');
  }

}
