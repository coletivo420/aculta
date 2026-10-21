<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Hook\StudioAccountHooks;
use Drupal\aculta_studio\StudioPresenter;
use Drupal\aculta_studio\StudioTabInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * @coversClass \Drupal\aculta_studio\Hook\StudioAccountHooks
 */
#[CoversClass(StudioAccountHooks::class)]
#[Group('aculta_studio')]
final class StudioAccountHooksTest extends UnitTestCase {

  public function testAddsTheStudioSectionWhenTheAccountHasAVisibleTab(): void {
    $sections = [];
    $this->hooks(TRUE)->addStudio($sections, $this->account(FALSE));

    self::assertArrayHasKey('aculta_studio.home', $sections);
    self::assertSame('aculta_studio.home', $sections['aculta_studio.home']['route']);
  }

  public function testDoesNotAddTheSectionWithoutVisibleTabsOrForAnonymous(): void {
    $sections = [];
    $this->hooks(FALSE)->addStudio($sections, $this->account(FALSE));
    self::assertSame([], $sections);

    $this->hooks(TRUE)->addStudio($sections, $this->account(TRUE));
    self::assertSame([], $sections);
  }

  private function account(bool $anonymous): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn($anonymous);
    return $account;
  }

  private function hooks(bool $hasVisibleTab): StudioAccountHooks {
    $tab = $this->createMock(StudioTabInterface::class);
    $tab->method('isAvailable')->willReturn(TRUE);
    $tab->method('access')->willReturn($hasVisibleTab ? AccessResult::allowed() : AccessResult::forbidden());
    $manager = $this->createMock(PluginManagerInterface::class);
    $manager->method('getDefinitions')->willReturn(['revista' => ['id' => 'revista', 'weight' => 10]]);
    $manager->method('createInstance')->willReturn($tab);
    return new StudioAccountHooks(new StudioPresenter($manager));
  }

}
