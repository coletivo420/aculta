<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Controller;

use Drupal\aculta_portal\Appearance\ColorModePreference;
use Drupal\aculta_portal\Controller\ColorModePreferenceController;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests authenticated color-mode preference updates.
 *
 * @group aculta_portal
 */
final class ColorModePreferenceControllerTest extends UnitTestCase {

  /**
   * Tests valid preference saving and per-user cache invalidation. */
  public function testSavesAllowedModeForCurrentUser(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::once())
      ->method('set')
      ->with(ColorModePreference::MODULE, 42, ColorModePreference::KEY, 'auto');
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::once())->method('invalidateTags')->with(['user:42']);

    $response = $this->controller(42, TRUE, $userData, $invalidator)->update(
      Request::create('/preferencias/modo-de-cor', 'POST', server: [], content: '{"mode":"auto"}'),
    );

    self::assertSame(200, $response->getStatusCode());
    self::assertSame(['saved' => TRUE, 'mode' => 'auto'], json_decode((string) $response->getContent(), TRUE));
    self::assertTrue($response->headers->hasCacheControlDirective('private'));
    self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
  }

  /**
   * Tests malformed or additional fields are rejected without writes. */
  public function testRejectsInvalidPayload(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::never())->method('set');
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::never())->method('invalidateTags');

    $response = $this->controller(42, TRUE, $userData, $invalidator)->update(
      Request::create('/preferencias/modo-de-cor', 'POST', server: [], content: '{"mode":"sepia","uid":7}'),
    );

    self::assertSame(400, $response->getStatusCode());
    self::assertSame(['saved' => FALSE, 'error' => 'invalid_mode'], json_decode((string) $response->getContent(), TRUE));
  }

  /**
   * Tests that the endpoint itself never saves anonymous preferences. */
  public function testRejectsAnonymousAccount(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::never())->method('set');
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::never())->method('invalidateTags');

    $response = $this->controller(0, FALSE, $userData, $invalidator)->update(
      Request::create('/preferencias/modo-de-cor', 'POST', server: [], content: '{"mode":"dark"}'),
    );

    self::assertSame(403, $response->getStatusCode());
  }

  /**
   * Ensures Drupal enforces POST and CSRF header validation on the route. */
  public function testUpdateRouteRequiresAuthenticatedCsrfProtectedPost(): void {
    $routes = Yaml::decode((string) file_get_contents(dirname(__DIR__, 4) . '/aculta_portal.routing.yml'));
    if (!is_array($routes)) {
      self::fail('The Portal route file must decode to an array.');
    }
    $route = $routes['aculta_portal.color_mode_preference.update'] ?? NULL;
    if (!is_array($route)) {
      self::fail('The color preference update route must be defined.');
    }
    $requirements = $route['requirements'] ?? NULL;
    if (!is_array($requirements)) {
      self::fail('The color preference route must declare requirements.');
    }
    self::assertSame(['POST'], $route['methods'] ?? NULL);
    self::assertSame('TRUE', $requirements['_user_is_logged_in'] ?? NULL);
    self::assertSame('TRUE', $requirements['_csrf_request_header_token'] ?? NULL);
  }

  /**
   * Builds a controller backed by the real preference service. */
  private function controller(
    int $uid,
    bool $authenticated,
    UserDataInterface $userData,
    CacheTagsInvalidatorInterface $invalidator,
  ): ColorModePreferenceController {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn((string) $uid);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $preference = new ColorModePreference($this->createMock(ConfigFactoryInterface::class), $userData);

    return new ColorModePreferenceController(
      $preference,
      $account,
      $invalidator,
      $this->createMock(LoggerInterface::class),
    );
  }

}
