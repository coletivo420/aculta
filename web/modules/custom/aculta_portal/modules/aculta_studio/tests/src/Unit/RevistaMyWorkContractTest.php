<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards the author-scoped editorial navigation contract.
 */
#[Group('aculta_studio')]
final class RevistaMyWorkContractTest extends UnitTestCase {

  /**
   * A personal listing requires an explicit author scope.
   */
  public function testPersonalListingRequiresExplicitAuthorScope(): void {
    $source = file_get_contents(__DIR__ . '/../../../src/Plugin/MarleyTab/RevistaTab.php');
    self::assertIsString($source);
    self::assertStringContainsString("'scope' => 'mine'", $source);
    self::assertStringContainsString("Meu trabalho", $source);
    self::assertStringContainsString("Publicações acessíveis", $source);
  }

  /**
   * Meu trabalho keeps create and moderation out of the sidebar.
   */
  public function testMyWorkMovesCreateAndModerationOutOfTheSidebar(): void {
    $source = file_get_contents(__DIR__ . '/../../../src/Plugin/MarleyTab/RevistaTab.php');
    self::assertIsString($source);
    self::assertStringContainsString("'suppress_sidebar' => TRUE", $source);
    self::assertStringContainsString("'sidebar' => FALSE", $source);
    self::assertStringContainsString("'secao' => 'criar'", $source);
    self::assertStringContainsString("'weight' => count(\$listTabs)", $source);
    self::assertStringContainsString("'label' => \$this->t('Comentários e moderação')", $source);
    self::assertStringContainsString("'listing' => \$moderationListing", $source);
    self::assertStringContainsString("if (\$canModerateComments) {", $source);
  }

  /**
   * Seek pages are bounded, and authorization is checked on each page.
   */
  public function testSeekPagesAreBoundedAndAuthorizationIsChecked(): void {
    $source = file_get_contents(__DIR__ . '/../../../src/MarleyListingBuilder.php');
    self::assertIsString($source);
    self::assertStringContainsString("->condition('uid', (int) \$account->id())", $source);
    self::assertStringContainsString("->range(0, 100)", $source);
    self::assertStringContainsString("for (\$batch = 0; \$batch < 10", $source);
    self::assertStringContainsString("self::canViewRevision(\$latest, \$account)", $source);
    self::assertStringContainsString("studio_cursor", $source);
    self::assertStringNotContainsString("createPager(count(\$matched)", $source);
  }

}
