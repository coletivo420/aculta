<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\Core\Access\AccessResultInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns 404 for the retired standalone Comment permalink (D-65).
 */
final class CommentPermalinkAccessCheck {

  /**
   * Denies every direct request to a standalone Comment URL.
   *
   * The comments remain available through their owning entity and contextual
   * access policies. This route must not reveal whether a Comment exists.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function deny(): AccessResultInterface {
    throw new NotFoundHttpException();
  }

}
