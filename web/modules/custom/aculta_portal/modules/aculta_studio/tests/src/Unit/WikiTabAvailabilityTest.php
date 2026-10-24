<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_studio\Plugin\StudioTab\WikiTab;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the owner module gate for the Studio Wiki tab.
 */
#[CoversClass(WikiTab::class)]
#[Group('aculta_studio')]
final class WikiTabAvailabilityTest extends UnitTestCase {

  /**
   * Ensures retained bundle configuration does not expose an inactive owner.
   */
  public function testUnavailableWhenWikiModuleIsDisabledButBundleRemains(): void {
    self::assertFalse($this->tab(FALSE, TRUE)->isAvailable());
  }

  /**
   * Ensures the Wiki tab is available when its owner and bundle exist.
   */
  public function testAvailableWhenWikiModuleAndBundleExist(): void {
    self::assertTrue($this->tab(TRUE, TRUE)->isAvailable());
  }

  /**
   * Ensures a missing Wiki bundle keeps the tab unavailable.
   */
  public function testUnavailableWhenWikiBundleIsMissing(): void {
    self::assertFalse($this->tab(TRUE, FALSE)->isAvailable());
  }

  /**
   * Builds a Wiki tab with controlled owner module and bundle state.
   */
  private function tab(bool $moduleEnabled, bool $bundleExists): WikiTab {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('aculta_wiki')->willReturn($moduleEnabled);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('wiki_entry')->willReturn(
      $bundleExists ? $this->createMock(NodeTypeInterface::class) : NULL,
    );
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node_type')->willReturn($storage);

    $domainPurposes = new DomainPurposeManager(
      $entityTypeManager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    return new WikiTab(
      [],
      'wiki',
      [],
      $moduleHandler,
      $this->getStringTranslationStub(),
      $domainPurposes,
      $entityTypeManager,
    );
  }

}
