<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ensures Studio and LMS expose only their canonical routes.
 */
#[CoversNothing]
#[Group('aculta_studio')]
final class StudioIdentityRoutingTest extends UnitTestCase {

  /**
   * All current experience paths have one canonical owner.
   */
  public function testCanonicalPathsHaveOneOwner(): void {
    $routes = $this->routes();
    $expected = [
      '/studio' => 'aculta_studio.hub',
      '/studio/pessoal' => 'aculta_studio.personal',
      '/studio/pessoal/perfil' => 'aculta_studio.personal_profile',
      '/studio/marley' => 'aculta_studio.home',
      '/studio/marley/{aba}' => 'aculta_studio.area',
      '/studio/marley/{aba}/{secao}' => 'aculta_studio.section',
      '/studio/marley/{aba}/{secao}/{sub}' => 'aculta_studio.tab',
      '/studio/ganjalab' => 'aculta_lms.student_dashboard',
      '/studio/we-sativa' => 'aculta_studio.we_sativa',
      '/studio/we-sativa/inicio' => 'aculta_studio.we_sativa_start',
    ];

    foreach ($expected as $path => $owner) {
      $matches = array_filter($routes, static fn (array $route): bool => ($route['path'] ?? NULL) === $path);
      self::assertCount(1, $matches, sprintf('Canonical path %s must have exactly one route.', $path));
      self::assertSame($path, $routes[$owner]['path']);
    }
  }

  /**
   * Studio hub and personal area are restricted to the account purpose.
   */
  public function testHubAndPersonalAccess(): void {
    $routes = $this->routes();
    foreach (['aculta_studio.hub', 'aculta_studio.personal', 'aculta_studio.personal_profile'] as $id) {
      $route = $routes[$id];
      self::assertSame('account', $route['options']['_aculta_domain_purpose']);
      self::assertSame('access aculta portal', $route['requirements']['_permission']);
      self::assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
    }
    self::assertSame('aculta_studio.hub_controller:page',
      $routes['aculta_studio.hub']['defaults']['_controller']);
    self::assertSame('aculta_studio.marley_controller:page',
      $routes['aculta_studio.home']['defaults']['_controller']);
  }

  /**
   * Removed development aliases must not be registered as live routes.
   */
  public function testNoObsoleteStudioOrStudentAliases(): void {
    $routes = $this->routes();
    foreach ($routes as $id => $definition) {
      self::assertDoesNotMatchRegularExpression('/(?:legacy|old_)/i', $id);
    }
    $obsolete = [
      '/studio/Marley',
      '/studio/inicio',
      '/ganjalab',
      '/ganjalab420',
      '/minha-aprendizagem',
      '/studio/ganjalab420',
      '/studio/ganjalab/{aba}/{secao}/{sub}',
    ];
    foreach ($obsolete as $path) {
      $matches = array_filter($routes, static fn (array $route): bool => ($route['path'] ?? NULL) === $path);
      self::assertCount(0, $matches, sprintf('%s must not resolve as an alias.', $path));
    }
  }

  /**
   * Reads the installed Studio/LMS route definitions.
   *
   * @return array<string, array<string, mixed>>
   *   Combined route maps.
   */
  private function routes(): array {
    $module = dirname(__DIR__, 3);
    return Yaml::decode(file_get_contents($module . '/aculta_studio.routing.yml'))
      + Yaml::decode(file_get_contents($module . '/../aculta_lms/aculta_lms.routing.yml'));
  }

}
