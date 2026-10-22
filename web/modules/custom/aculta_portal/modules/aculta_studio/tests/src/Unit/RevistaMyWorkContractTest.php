<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Plugin\StudioTab\RevistaTab;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards the author-scoped editorial navigation contract.
 */
#[Group('aculta_studio')]
final class RevistaMyWorkContractTest extends UnitTestCase {

  public function testPersonalListingRequiresExplicitAuthorScope(): void {
    $source = file_get_contents(__DIR__ . '/../../../src/Plugin/StudioTab/RevistaTab.php');
    self::assertIsString($source);
    self::assertStringContainsString("'scope' => 'mine'", $source);
    self::assertStringContainsString("Meu trabalho", $source);
    self::assertStringContainsString("Meus conteúdos", $source);
  }

  public function testOwnerScopeAndRevisionFilterPrecedePageSlice(): void {
    $source = file_get_contents(__DIR__ . '/../../../src/StudioListingBuilder.php');
    self::assertIsString($source);
    $owner = strpos($source, "->condition('uid', (int) \$account->id())");
    $revision = strpos($source, 'foreach (array_chunk(array_values($nids), 100)');
    $pager = strpos($source, 'createPager(count($matched), 20)');
    $slice = strpos($source, 'array_slice($matched, $pager->getCurrentPage() * 20, 20)');
    self::assertNotFalse($owner);
    self::assertNotFalse($revision);
    self::assertNotFalse($pager);
    self::assertNotFalse($slice);
    self::assertLessThan($revision, $owner);
    self::assertLessThan($pager, $revision);
    self::assertLessThan($slice, $pager);
  }

}
