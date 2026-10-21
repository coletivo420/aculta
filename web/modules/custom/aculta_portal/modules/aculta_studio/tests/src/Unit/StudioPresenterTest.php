<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\StudioPresenter;
use Drupal\aculta_studio\StudioTabInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * @coversClass \Drupal\aculta_studio\StudioPresenter
 */
#[CoversClass(StudioPresenter::class)]
#[Group('aculta_studio')]
final class StudioPresenterTest extends UnitTestCase {

  public function testShowsOnlyAvailableTabsAllowedToTheAccountInWeightOrder(): void {
    $definitions = [
      'galerias' => ['id' => 'galerias', 'weight' => 20],
      'revista' => ['id' => 'revista', 'weight' => 10],
      'eventos' => ['id' => 'eventos', 'weight' => 30],
    ];
    $tabs = [
      'revista' => $this->tab(TRUE, AccessResult::allowed()),
      'galerias' => $this->tab(TRUE, AccessResult::forbidden()),
      'eventos' => $this->tab(FALSE, AccessResult::allowed()),
    ];
    $manager = $this->createMock(PluginManagerInterface::class);
    $manager->method('getDefinitions')->willReturn($definitions);
    $manager->method('createInstance')->willReturnCallback(static fn (string $id) => $tabs[$id]);

    $visible = (new StudioPresenter($manager))->visibleTabs($this->createMock(AccountInterface::class));

    self::assertSame(['revista'], array_keys($visible));
  }

  public function testKeepsWeightOrderAcrossSeveralVisibleTabs(): void {
    $definitions = [
      'b' => ['id' => 'b', 'weight' => 20],
      'a' => ['id' => 'a', 'weight' => 10],
    ];
    $tabs = [
      'a' => $this->tab(TRUE, AccessResult::allowed()),
      'b' => $this->tab(TRUE, AccessResult::allowed()),
    ];
    $manager = $this->createMock(PluginManagerInterface::class);
    $manager->method('getDefinitions')->willReturn($definitions);
    $manager->method('createInstance')->willReturnCallback(static fn (string $id) => $tabs[$id]);

    $visible = (new StudioPresenter($manager))->visibleTabs($this->createMock(AccountInterface::class));

    self::assertSame(['a', 'b'], array_keys($visible));
  }

  private function tab(bool $available, AccessResult $access): StudioTabInterface {
    $tab = $this->createMock(StudioTabInterface::class);
    $tab->method('isAvailable')->willReturn($available);
    $tab->method('access')->willReturn($access);
    return $tab;
  }

}
