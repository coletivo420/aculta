<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_secrets_manager\Unit;

use Drupal\aculta_secrets_manager\EventSubscriber\SecretsResponseSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Respostas do painel de credenciais não são cacheadas.
 */
#[\PHPUnit\Framework\Attributes\Group('aculta_secrets_manager')]
final class SecretsResponseSubscriberTest extends UnitTestCase {

  /**
   * Marks panel responses as non-cacheable.
   */
  public function testPanelRoutesAreNoStore(): void {
    foreach (SecretsResponseSubscriber::ROUTES as $route) {
      $response = $this->respond($route);
      $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'), $route);
      $this->assertSame('no-cache', $response->headers->get('Pragma'), $route);
    }
  }

  /**
   * Leaves unrelated route responses unchanged.
   */
  public function testOtherRoutesAreUntouched(): void {
    $response = $this->respond('system.admin');
    $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
  }

  /**
   * Dispatches a synthetic response for a route.
   */
  private function respond(string $route): Response {
    $request = Request::create('/painel-administrativo/aculta/segredos');
    $request->attributes->set('_route', $route);
    $response = new Response();
    $event = new ResponseEvent(
      $this->createMock(HttpKernelInterface::class),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
      $response,
    );
    (new SecretsResponseSubscriber())->onResponse($event);
    return $response;
  }

}
