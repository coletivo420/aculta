<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\EventSubscriber\UploadedAnswerRouteSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Verifies optional compatibility with the legacy file-download route.
 */
#[CoversClass(UploadedAnswerRouteSubscriber::class)]
final class UploadedAnswerRouteSubscriberTest extends UnitTestCase {

  /**
   * Route rebuilding works when the legacy provider is absent.
   */
  public function testAbsentLegacyRouteIsIgnored(): void {
    $routes = new RouteCollection();

    $this->alterRoutes($routes);

    self::assertCount(0, $routes);
  }

  /**
   * The ACULTA access check is applied when the legacy route exists.
   */
  public function testLegacyRouteKeepsAcultaAccessCheck(): void {
    $routes = new RouteCollection();
    $routes->add('lms_file_upload.answer_file_download', new Route('/legacy-download'));

    $this->alterRoutes($routes);

    $route = $routes->get('lms_file_upload.answer_file_download');
    self::assertSame('/resposta/{lms_answer}/baixar-arquivo', $route->getPath());
    self::assertSame('aculta_lms.uploaded_answer_access::access', $route->getRequirement('_custom_access'));
  }

  /**
   * Invokes the protected RouteSubscriberBase hook.
   */
  private function alterRoutes(RouteCollection $routes): void {
    $method = new \ReflectionMethod(UploadedAnswerRouteSubscriber::class, 'alterRoutes');
    $method->invoke(new UploadedAnswerRouteSubscriber(), $routes);
  }

}
