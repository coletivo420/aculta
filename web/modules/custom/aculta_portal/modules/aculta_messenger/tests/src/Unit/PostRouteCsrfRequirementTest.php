<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Access\AccessResult;
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
    $routes = self::routesFromFile($route_file);
    $post_routes = [];
    foreach ($routes as $name => $route) {
      if (self::routeMethods($route) === ['POST']) {
        $post_routes[] = $name;
        $requirements = self::arrayValue($route, 'requirements');
        self::assertSame('TRUE', $requirements['_csrf_token'] ?? NULL, $name);
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
    $definitions = self::routesFromFile($route_file);
    $scenarios = [];
    foreach ($definitions as $name => $definition) {
      $methods = self::routeMethods($definition);
      if ($methods !== ['POST']) {
        continue;
      }
      $routePath = self::stringAt($definition, 'path');
      preg_match_all('/\{([^}]+)\}/', $routePath, $matches);
      $parameters = [];
      foreach ($matches[1] as $parameter) {
        $parameters[$parameter] = $parameter === 'user' ? '84' : '42';
      }
      $path = preg_replace_callback(
        '/\{([^}]+)\}/',
        static fn(array $match): string => $parameters[$match[1]] ?? '42',
        ltrim($routePath, '/'),
      );
      $scenarios[$name] = [
        'route' => new Route(
          $routePath,
          self::arrayValue($definition, 'defaults'),
          self::stringableMap($definition, 'requirements'),
          self::arrayValue($definition, 'options'),
          '',
          [],
          $methods,
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
      self::assertInstanceOf(AccessResult::class, $result);
      self::assertSame(0, $result->getCacheMaxAge());
    }
  }

  /**
   * Reads and validates all route definitions from the module YAML file.
   *
   * @return array<string, array<string, mixed>>
   *   Route definitions keyed by route name.
   */
  private static function routesFromFile(string $file): array {
    $contents = file_get_contents($file);
    self::assertIsString($contents);
    $decoded = Yaml::decode($contents);
    self::assertIsArray($decoded);
    $routes = [];
    foreach ($decoded as $name => $definition) {
      if (!is_string($name) || !is_array($definition)) {
        self::fail('Cada rota YAML deve ter um nome e uma definição em array.');
      }
      $validatedDefinition = [];
      foreach ($definition as $key => $value) {
        if (!is_string($key)) {
          self::fail('As chaves de uma definição de rota devem ser strings.');
        }
        $validatedDefinition[$key] = $value;
      }
      $routes[$name] = $validatedDefinition;
    }
    return $routes;
  }

  /**
   * Returns route methods after confirming the YAML field is a string list.
   *
   * @param array<string, mixed> $route
   *   Route definition from the module YAML file.
   *
   * @return list<string>
   *   Configured HTTP methods.
   */
  private static function routeMethods(array $route): array {
    $methods = $route['methods'] ?? [];
    if (!is_array($methods) || !array_is_list($methods)) {
      self::fail('O campo methods deve ser uma lista.');
    }
    foreach ($methods as $method) {
      if (!is_string($method)) {
        self::fail('Cada método de rota deve ser uma string.');
      }
    }
    return $methods;
  }

  /**
   * Returns a required string route property.
   *
   * @param array<string, mixed> $route
   *   Route definition from the module YAML file.
   */
  private static function stringAt(array $route, string $key): string {
    self::assertArrayHasKey($key, $route);
    self::assertIsString($route[$key]);
    return $route[$key];
  }

  /**
   * Returns a route property that must be an array.
   *
   * @param array<string, mixed> $route
   *   Route definition from the module YAML file.
   *
   * @return array<mixed>
   *   Route defaults, options or parameters.
   */
  private static function arrayValue(array $route, string $key): array {
    $value = $route[$key] ?? [];
    self::assertIsArray($value);
    return $value;
  }

  /**
   * Returns a map accepted by Symfony's route requirements parameter.
   *
   * @param array<string, mixed> $route
   *   Route definition from the module YAML file.
   *
   * @return array<string, string|\Stringable>
   *   Stringable route requirement values.
   */
  private static function stringableMap(array $route, string $key): array {
    $values = self::arrayValue($route, $key);
    $validated = [];
    foreach ($values as $name => $value) {
      if (!is_string($name) || (!is_string($value) && !$value instanceof \Stringable)) {
        self::fail('Requisitos de rota devem ter chave string e valor stringable.');
      }
      $validated[$name] = $value;
    }
    return $validated;
  }

}
