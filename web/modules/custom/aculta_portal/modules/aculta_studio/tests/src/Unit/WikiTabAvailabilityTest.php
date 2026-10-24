<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_studio\Plugin\StudioTab\WikiTab;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
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
   * Ensures contributor creation access stays in WE SATIVA, not GanjaLab.
   */
  public function testCreateOnlyContributorCannotAccessEditorialTab(): void {
    $account = $this->account(['create wiki_entry content']);
    $tab = $this->tab(TRUE, TRUE);

    self::assertFalse($tab->access($account)->isAllowed());
    self::assertSame([], $tab->sections($account));
  }

  /**
   * Ensures editor inventory is available only to an any-entry editor.
   */
  public function testEditorCanAccessEntryInventory(): void {
    $account = $this->account(['edit any wiki_entry content']);
    $sections = $this->tab(TRUE, TRUE)->sections($account);

    self::assertTrue($this->tab(TRUE, TRUE)->access($account)->isAllowed());
    self::assertArrayHasKey('verbetes', $sections);
    self::assertArrayNotHasKey('moderacao', $sections);
  }

  /**
   * Ensures proposal moderators access only the moderation section.
   */
  public function testProposalModeratorCanAccessModerationSection(): void {
    $account = $this->account(['moderate wiki proposals']);
    $sections = $this->tab(TRUE, TRUE)->sections($account);

    self::assertTrue($this->tab(TRUE, TRUE)->access($account)->isAllowed());
    self::assertArrayNotHasKey('verbetes', $sections);
    self::assertArrayHasKey('moderacao', $sections);
    self::assertArrayHasKey('propostas', $sections['moderacao']['tabs']);
  }

  /**
   * Ensures revision access does not expose the shared entry inventory.
   */
  public function testRevisionReviewerCanAccessRevisionSection(): void {
    $account = $this->account(['view wiki_entry revisions']);
    $sections = $this->tab(TRUE, TRUE)->sections($account);

    self::assertTrue($this->tab(TRUE, TRUE)->access($account)->isAllowed());
    self::assertArrayNotHasKey('verbetes', $sections);
    self::assertArrayHasKey('revisoes', $sections);
    self::assertArrayNotHasKey('moderacao', $sections);
  }

  /**
   * Creates an account mock with an allowlisted set of permissions.
   *
   * @param string[] $permissions
   *   Permissions granted to the account.
   */
  private function account(array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $permissions, TRUE),
    );
    return $account;
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
