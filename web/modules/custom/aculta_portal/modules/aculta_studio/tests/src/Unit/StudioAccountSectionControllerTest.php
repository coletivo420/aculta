<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_portal\Controller\PortalController;
use Drupal\aculta_studio\Controller\StudioAccountSectionController;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Verifies Portal Form API composition without nesting the account shell.
 */
#[CoversClass(StudioAccountSectionController::class)]
#[Group('aculta_studio')]
final class StudioAccountSectionControllerTest extends UnitTestCase {

  /**
   * Authorization is checked before resolving an owner controller.
   */
  public function testVisitorCannotLoadAccountSections(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioAccountSectionController($resolver, $this->account(FALSE, TRUE)))->address();
  }

  /**
   * Portal permission is checked even for authenticated members.
   */
  public function testRestrictedMemberDenied(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioAccountSectionController($resolver, $this->account(TRUE, FALSE)))->security();
  }

  /**
   * Each permitted operation delegates to the original owner exactly once.
   */
  public function testDelegatesAndReplacesOnlyLegacyNavigation(): void {
    $methods = [
      'address' => 'address',
      'security' => 'security',
      'password' => 'securityPassword',
      'connections' => 'connections',
    ];

    foreach ($methods as $studioMethod => $ownerMethod) {
      $owner = new class {
        public array $called = [];

        public function address(): array {
          $this->called[] = 'address';
          return $this->build('address');
        }

        public function security(): array {
          $this->called[] = 'security';
          return $this->build('security');
        }

        public function securityPassword(): array {
          $this->called[] = 'securityPassword';
          return $this->build('password');
        }

        public function connections(): array {
          $this->called[] = 'connections';
          return $this->build('connections');
        }

        private function build(string $name): array {
          return [
            'content' => [
              'tabs' => ['#type' => 'container', '#legacy' => TRUE],
              'section' => ['#owner' => $name, 'form' => ['#type' => 'form']],
            ],
            '#cache' => ['max-age' => 0],
          ];
        }
      };

      $resolver = $this->createMock(ClassResolverInterface::class);
      $resolver->expects(self::once())->method('getInstanceFromDefinition')
        ->with(PortalController::class)->willReturn($owner);

      $page = (new StudioAccountSectionController($resolver, $this->account(TRUE, TRUE)))->{$studioMethod}();

      self::assertSame([$ownerMethod], $owner->called);
      self::assertSame('studio-account-section', $page['#attributes']['data-portal-ui']);
      self::assertSame($studioMethod === 'password' ? 'password' : $ownerMethod,
        $page['content']['content']['section']['#owner']);
      self::assertSame(['#type' => 'form'],
        $page['content']['content']['section']['form']);
      self::assertArrayNotHasKey('tabs', $page['content']['content']);
      self::assertSame('item_list', $page['navigation']['#theme']);
      self::assertCount(5, $page['navigation']['#items']);
      $routes = array_map(static fn (array $link) => $link['#url']->getRouteName(),
        $page['navigation']['#items']);
      self::assertSame([
        'aculta_portal.my_data',
        'aculta_studio.account_address',
        'aculta_studio.account_security',
        'aculta_studio.account_password',
        'aculta_studio.account_connections',
      ], $routes);
      self::assertSame(0, $page['#cache']['max-age']);
      self::assertContains('user', $page['#cache']['contexts']);
    }
  }

  /**
   * Creates a user account with controlled authentication/permission.
   */
  private function account(bool $authenticated, bool $permitted): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->with('access aculta portal')->willReturn($permitted);
    return $account;
  }

}
