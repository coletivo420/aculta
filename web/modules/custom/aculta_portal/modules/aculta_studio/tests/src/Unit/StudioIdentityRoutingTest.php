<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies canonical identity routes and safe legacy methods in route metadata.
 *
 * @group aculta_studio
 */
#[CoversNothing]
#[Group('aculta_studio')]
final class StudioIdentityRoutingTest extends UnitTestCase {

  /**
   * Confirms that each canonical Studio path has one route owner.
   */
  public function testCanonicalPathsHaveOneOwner(): void {
    $module = dirname(__DIR__, 3);
    $routes = Yaml::decode(file_get_contents($module . '/aculta_studio.routing.yml'));
    $routes += Yaml::decode(file_get_contents($module . '/../aculta_lms/aculta_lms.routing.yml'));
    $expected = [
      '/studio/marley' => 'aculta_studio.home',
      '/studio/marley/{aba}' => 'aculta_studio.area',
      '/studio/marley/{aba}/{secao}' => 'aculta_studio.section',
      '/studio/marley/{aba}/{secao}/{sub}' => 'aculta_studio.tab',
      '/studio/ganjalab' => 'aculta_lms.student_dashboard',
      '/studio/we-sativa' => 'aculta_studio.we_sativa',
      '/studio/we-sativa/inicio' => 'aculta_studio.we_sativa_start',
      '/studio/pessoal' => 'aculta_studio.personal',
      '/studio/inicio' => 'aculta_studio.hub_preview',
    ];

    foreach ($expected as $path => $owner) {
      $matches = array_filter($routes, static fn (array $route): bool => ($route['path'] ?? NULL) === $path);
      self::assertCount(1, $matches, sprintf('Canonical path %s must have a single route.', $path));
      self::assertArrayHasKey($owner, $routes);
    }
  }

  /**
   * Personal entry is account-only and does not replace the legacy Marley home.
   */
  public function testPersonalRouteIsExplicitProtectedAndDoesNotChangeStudioHome(): void {
    $module = dirname(__DIR__, 3);
    $routes = Yaml::decode(file_get_contents($module . '/aculta_studio.routing.yml'));

    $personal = $routes['aculta_studio.personal'];
    self::assertSame('/studio/pessoal', $personal['path']);
    self::assertSame('account', $personal['options']['_aculta_domain_purpose']);
    self::assertSame('access aculta portal', $personal['requirements']['_permission']);
    self::assertSame('TRUE', $personal['requirements']['_user_is_logged_in']);
    self::assertSame('/studio', $routes['aculta_studio.legacy_home']['path']);
    self::assertSame('aculta_studio.marley_controller:legacyPage',
      $routes['aculta_studio.legacy_home']['defaults']['_controller']);

    $order = array_keys($routes);
    self::assertTrue(array_search('aculta_studio.personal', $order, TRUE)
      < array_search('aculta_studio.legacy_area', $order, TRUE));
  }

  /**
   * The preview route is explicit, safe and leaves /studio on Marley.
   */
  public function testHubPreviewDoesNotTakeOverTheLegacyStudioHome(): void {
    $module = dirname(__DIR__, 3);
    $routes = Yaml::decode(file_get_contents($module . '/aculta_studio.routing.yml'));
    $preview = $routes['aculta_studio.hub_preview'];

    self::assertSame('/studio/inicio', $preview['path']);
    self::assertSame('account', $preview['options']['_aculta_domain_purpose']);
    self::assertSame(['GET', 'HEAD'], $preview['methods']);
    self::assertSame('TRUE', $preview['requirements']['_user_is_logged_in']);
    self::assertSame('access aculta portal', $preview['requirements']['_permission']);
    self::assertSame('/studio', $routes['aculta_studio.legacy_home']['path']);
    self::assertSame('aculta_studio.marley_controller:legacyPage',
      $routes['aculta_studio.legacy_home']['defaults']['_controller']);
    self::assertTrue(array_search('aculta_studio.hub_preview', array_keys($routes), TRUE)
      < array_search('aculta_studio.legacy_area', array_keys($routes), TRUE));
  }

  /**
   * Confirms that all legacy redirects are limited to safe methods.
   */
  public function testLegacyRedirectRoutesAcceptOnlySafeMethods(): void {
    $module = dirname(__DIR__, 3);
    $routes = Yaml::decode(file_get_contents($module . '/aculta_studio.routing.yml'));
    $routes += Yaml::decode(file_get_contents($module . '/../aculta_lms/aculta_lms.routing.yml'));
    $legacy_ids = [
      'aculta_studio.legacy_editorial_deep',
      'aculta_studio.old_ganjalab_home',
      'aculta_studio.old_ganjalab_area',
      'aculta_studio.old_ganjalab_section',
      'aculta_studio.old_ganjalab_tab',
      'aculta_lms.legacy_student_dashboard',
      'aculta_lms.old_student_dashboard',
      'aculta_lms.old_studio_student_dashboard',
    ];

    foreach ($legacy_ids as $route_id) {
      self::assertSame(['GET', 'HEAD'], $routes[$route_id]['methods'] ?? [], $route_id . ' must not redirect unsafe methods.');
    }
  }

}
