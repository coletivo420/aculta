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

  public function testSeekPagesAreBoundedAndAuthorizationIsChecked(): void {
    $source = file_get_contents(__DIR__ . '/../../../src/StudioListingBuilder.php');
    self::assertIsString($source);
    self::assertStringContainsString("->condition('uid', (int) \$account->id())", $source);
    self::assertStringContainsString("->range(0, 100)", $source);
    self::assertStringContainsString("for (\$batch = 0; \$batch < 10", $source);
    self::assertStringContainsString("self::canViewRevision(\$latest, \$account)", $source);
    self::assertStringContainsString("studio_cursor", $source);
    self::assertStringNotContainsString("createPager(count(\$matched)", $source);
  }

}
