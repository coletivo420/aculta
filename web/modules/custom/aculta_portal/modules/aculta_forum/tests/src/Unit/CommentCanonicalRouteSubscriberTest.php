<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Access\CommentPermalinkAccessCheck;
use Drupal\aculta_forum\EventSubscriber\CommentCanonicalRouteSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests the D-65 route guard for standalone Comment URLs.
 */
final class CommentCanonicalRouteSubscriberTest extends UnitTestCase {

  /**
   * The canonical route remains generatable but has only the 404 access check.
   */
  public function testReplacesAccessRequirementsWithNotFoundGuard(): void {
    $routes = new RouteCollection();
    $routes->add('entity.comment.canonical', new Route('/comment/{comment}', [], [
      '_entity_access' => 'comment.view',
      '_permission' => 'access content',
    ]));
    $routes->add('entity.node.canonical', new Route('/node/{node}', [], ['_entity_access' => 'node.view']));

    $subscriber = new CommentCanonicalRouteSubscriber();
    $alter = new \ReflectionMethod($subscriber, 'alterRoutes');
    $alter->invoke($subscriber, $routes);

    self::assertSame('/comment/{comment}', $routes->get('entity.comment.canonical')->getPath());
    self::assertSame([
      '_custom_access' => 'aculta_forum.comment_permalink_access::deny',
    ], $routes->get('entity.comment.canonical')->getRequirements());
    self::assertSame(['_entity_access' => 'node.view'], $routes->get('entity.node.canonical')->getRequirements());
  }

  /**
   * The route access guard produces an actual not-found exception.
   */
  public function testStandalonePermalinkAccessThrowsNotFound(): void {
    $this->expectException(NotFoundHttpException::class);
    (new CommentPermalinkAccessCheck())->deny();
  }

}
