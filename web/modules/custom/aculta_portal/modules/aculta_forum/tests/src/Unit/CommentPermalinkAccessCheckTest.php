<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Access\CommentPermalinkAccessCheck;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests that standalone comment access never reveals resource existence.
 */
final class CommentPermalinkAccessCheckTest extends UnitTestCase {

  /**
   * Every request to a standalone Comment URL is hidden as not found.
   */
  public function testDirectCommentPermalinkAlwaysThrowsNotFound(): void {
    $this->expectException(NotFoundHttpException::class);
    (new CommentPermalinkAccessCheck())->deny();
  }

}
