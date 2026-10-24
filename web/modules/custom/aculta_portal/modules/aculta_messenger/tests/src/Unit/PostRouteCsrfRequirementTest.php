<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Access\CsrfAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Verifies CSRF access requirements on state-changing Messenger routes.
 */
#[CoversNothing]
#[Group('aculta_messenger')]
final class PostRouteCsrfRequirementTest extends UnitTestCase {

  /**
   * Every POST-only route requires Drupal's route CSRF token.
   */
  public function testPostOnlyRoutesRequireCsrfToken(): void {
    $route_file = dirname(__DIR__, 3) . '/aculta_messenger.routing.yml';
    $routes = Yaml::decode(file_get_contents($route_file));
    $post_routes = [];
    foreach ($routes as $name => $route) {
      if (($route['methods'] ?? []) === ['POST']) {
        $post_routes[] = $name;
        self::assertSame('TRUE', $route['requirements']['_csrf_token'] ?? NULL, $name);
      }
    }

    sort($post_routes);
    self::assertSame([
      'aculta_messenger.drawer_relationship',
      'aculta_messenger.drawer_reply',
      'aculta_messenger.member_relationship',
      'aculta_messenger.profile_relationship',
    ], $post_routes);
  }

  /**
   * Core rejects absent and invalid route tokens and accepts a valid one.
   */
  public function testDrupalCsrfAccessCheckValidatesRouteAndParameters(): void {
    $route = new Route(
      '/mensagens/conversa/{private_message_thread}/responder',
      [],
      ['_csrf_token' => 'TRUE'],
      [],
      '',
      [],
      ['POST'],
    );
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRawParameters')->willReturn(new ParameterBag([
      'private_message_thread' => '42',
    ]));
    $tokens = $this->createMock(CsrfTokenGenerator::class);
    $tokens->expects(self::exactly(3))
      ->method('validate')
      ->willReturnMap([
        ['', 'mensagens/conversa/42/responder', FALSE],
        ['invalid', 'mensagens/conversa/42/responder', FALSE],
        ['valid', 'mensagens/conversa/42/responder', TRUE],
      ]);
    $checker = new CsrfAccessCheck($tokens);

    foreach (['', 'invalid'] as $token) {
      $request = Request::create('/mensagens/conversa/42/responder', 'POST');
      if ($token !== '') {
        $request->query->set('token', $token);
      }
      self::assertFalse($checker->access($route, $request, $routeMatch)->isAllowed());
    }
    $request = Request::create('/mensagens/conversa/42/responder', 'POST');
    $request->query->set('token', 'valid');
    $result = $checker->access($route, $request, $routeMatch);
    self::assertTrue($result->isAllowed());
    self::assertSame(0, $result->getCacheMaxAge());
  }

}
