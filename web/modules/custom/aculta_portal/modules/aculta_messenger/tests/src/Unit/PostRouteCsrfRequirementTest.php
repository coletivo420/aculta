<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Access\CsrfAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Routing\RouteMatchInterface;
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
    $route_file = dirname(__DIR__, 3) . '/aculta_messenger.routing.yml';
    $definitions = Yaml::decode(file_get_contents($route_file));
    $scenarios = [];
    foreach ($definitions as $name => $definition) {
      if (($definition['methods'] ?? []) !== ['POST']) {
        continue;
      }
      preg_match_all('/\{([^}]+)\}/', $definition['path'], $matches);
      $parameters = [];
      foreach ($matches[1] as $parameter) {
        $parameters[$parameter] = $parameter === 'user' ? '84' : '42';
      }
      $path = preg_replace_callback(
        '/\{([^}]+)\}/',
        static fn(array $match): string => $parameters[$match[1]],
        ltrim($definition['path'], '/'),
      );
      $scenarios[$name] = [
        'route' => new Route(
          $definition['path'],
          $definition['defaults'] ?? [],
          $definition['requirements'] ?? [],
          $definition['options'] ?? [],
          '',
          [],
          $definition['methods'],
        ),
        'parameters' => $parameters,
        'path' => $path,
      ];
    }
    self::assertCount(4, $scenarios);
    $tokens = $this->createMock(CsrfTokenGenerator::class);
    $tokens->expects(self::exactly(count($scenarios) * 3))
      ->method('validate')
      ->willReturnCallback(static function (string $token, string $path) use ($scenarios): bool {
        self::assertContains($path, array_column($scenarios, 'path'));
        return $token === 'valid';
      });
    $checker = new CsrfAccessCheck($tokens);

    foreach ($scenarios as $scenario) {
      $routeMatch = $this->createMock(RouteMatchInterface::class);
      $routeMatch->method('getRawParameters')->willReturn(new ParameterBag($scenario['parameters']));
      foreach (['', 'invalid'] as $token) {
        $request = Request::create('/' . $scenario['path'], 'POST');
        if ($token !== '') {
          $request->query->set('token', $token);
        }
        self::assertFalse($checker->access($scenario['route'], $request, $routeMatch)->isAllowed());
      }
      $request = Request::create('/' . $scenario['path'], 'POST');
      $request->query->set('token', 'valid');
      $result = $checker->access($scenario['route'], $request, $routeMatch);
      self::assertTrue($result->isAllowed());
      self::assertSame(0, $result->getCacheMaxAge());
    }
  }

}
