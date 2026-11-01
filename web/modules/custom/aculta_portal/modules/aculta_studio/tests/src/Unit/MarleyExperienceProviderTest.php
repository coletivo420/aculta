<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\MarleyExperienceProvider;
use Drupal\aculta_studio\MarleyPresenter;
use Drupal\aculta_studio\MarleyTabInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Ensures the Marley bridge never broadens editorial permissions.
 */
#[CoversClass(MarleyExperienceProvider::class)]
#[Group('aculta_studio')]
final class MarleyExperienceProviderTest extends UnitTestCase {

  /**
   * Exposes the existing editorial route and account purpose.
   */
  public function testMetadataAndUnavailableAreas(): void {
    $account = $this->account(TRUE, TRUE);
    $provider = new MarleyExperienceProvider($this->presenter(FALSE), $account);

    self::assertSame('marley', $provider->id());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_studio.home', $provider->routeName());
    self::assertTrue($provider->available());
    self::assertFalse($provider->access($account)->isAllowed());
    self::assertSame(0, $provider->access($account)->getCacheMaxAge());

    $this->expectException(AccessDeniedHttpException::class);
    $provider->build();
  }

  /**
   * Marley is visible only if the user has a usable editorial action.
   */
  public function testRequiresPortalAccessAndVisibleMarleyTabs(): void {
    $presenter = $this->presenter(TRUE);
    $visitor = $this->account(FALSE, TRUE);
    $restricted = $this->account(TRUE, FALSE);
    $editor = $this->account(TRUE, TRUE);
    $provider = new MarleyExperienceProvider($presenter, $editor);

    self::assertFalse($provider->access($visitor)->isAllowed());
    self::assertFalse($provider->access($restricted)->isAllowed());
    self::assertTrue($provider->access($editor)->isAllowed());
    self::assertSame(0, $provider->access($editor)->getCacheMaxAge());
  }

  /**
   * Builds a presenter with a permitted or absent editorial area.
   */
  private function presenter(bool $hasArea): MarleyPresenter {
    $plugins = $this->createMock(PluginManagerInterface::class);
    if (!$hasArea) {
      $plugins->method('getDefinitions')->willReturn([]);
      return new MarleyPresenter($plugins);
    }

    $plugins->method('getDefinitions')->willReturn([
      'revista' => ['id' => 'revista', 'weight' => 0],
    ]);
    $tab = $this->createMock(MarleyTabInterface::class);
    $tab->method('isAvailable')->willReturn(TRUE);
    $tab->method('access')->willReturn(AccessResult::allowed());
    $tab->method('sections')->willReturn([
      'criar' => [
        'label' => 'Criar',
        'tabs' => [
          'publicacao' => [
            'label' => 'Nova publicação',
            'actions' => [
              ['title' => 'Criar', 'url' => NULL, 'access' => TRUE],
            ],
          ],
        ],
      ],
    ]);
    $plugins->method('createInstance')->willReturn($tab);
    return new MarleyPresenter($plugins);
  }

  /**
   * Provides an account with controlled login and portal permissions.
   */
  private function account(bool $authenticated, bool $permitted): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->willReturn($permitted);
    return $account;
  }

}
