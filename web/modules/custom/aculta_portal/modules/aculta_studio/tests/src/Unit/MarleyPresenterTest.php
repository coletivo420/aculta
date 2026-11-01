<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\MarleyPresenter;
use Drupal\aculta_studio\MarleyTabInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the Marley presenter.
 */
#[CoversClass(MarleyPresenter::class)]
#[Group('aculta_studio')]
final class MarleyPresenterTest extends UnitTestCase {

  /**
   * Shows only available tabs the account may use, in weight order.
   */
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

    $visible = (new MarleyPresenter($manager))->visibleTabs($this->createMock(AccountInterface::class));

    self::assertSame(['revista'], array_keys($visible));
  }

  /**
   * Keeps weight order across several visible tabs.
   */
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

    $visible = (new MarleyPresenter($manager))->visibleTabs($this->createMock(AccountInterface::class));

    self::assertSame(['a', 'b'], array_keys($visible));
  }

  /**
   * Keeps the sidebar controls and only allowed section actions.
   */
  public function testPreservesSidebarControlsAndAllowedSectionActions(): void {
    $tab = $this->createMock(MarleyTabInterface::class);
    $tab->method('isAvailable')->willReturn(TRUE);
    $tab->method('access')->willReturn(AccessResult::allowed());
    $tab->method('sections')->willReturn([
      'publicacoes' => [
        'label' => 'Meu trabalho',
        'weight' => 0,
        'suppress_sidebar' => TRUE,
        'actions' => [
          ['title' => 'Criar', 'url' => NULL, 'access' => TRUE],
          ['title' => 'Negada', 'url' => NULL, 'access' => FALSE],
        ],
        'tabs' => [
          'todos' => [
            'label' => 'Todos',
            'weight' => 0,
            'actions' => [['title' => 'Ação', 'url' => NULL, 'access' => TRUE]],
          ],
        ],
      ],
      'criar' => [
        'label' => 'Criar',
        'weight' => 10,
        'sidebar' => FALSE,
        'tabs' => [
          'nova' => [
            'label' => 'Nova',
            'weight' => 0,
            'actions' => [['title' => 'Nova', 'url' => NULL, 'access' => TRUE]],
          ],
        ],
      ],
    ]);
    $presenter = new MarleyPresenter($this->createMock(PluginManagerInterface::class));

    $sections = $presenter->sectionsFor($tab, $this->createMock(AccountInterface::class));

    self::assertTrue($sections['publicacoes']['suppress_sidebar']);
    self::assertCount(1, $sections['publicacoes']['actions']);
    self::assertSame('Criar', (string) $sections['publicacoes']['actions'][0]['title']);
    self::assertFalse($sections['criar']['sidebar']);
  }

  /**
   * Builds a tab double with the given availability and access result.
   */
  private function tab(bool $available, AccessResult $access): MarleyTabInterface {
    $tab = $this->createMock(MarleyTabInterface::class);
    $tab->method('isAvailable')->willReturn($available);
    $tab->method('access')->willReturn($access);
    $tab->method('sections')->willReturn([
      'criar' => [
        'label' => 'Criar',
        'weight' => 0,
        'tabs' => [
          'nova' => [
            'label' => 'Nova',
            'weight' => 0,
            'actions' => [['title' => 'Ação', 'url' => NULL, 'access' => TRUE]],
          ],
        ],
      ],
    ]);
    return $tab;
  }

}
