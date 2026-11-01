<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\Revista\RevistaPublications;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects the canonical bundle while legacy nodes are still being migrated.
 */
#[Group('aculta_magazine')]
final class RevistaPublicationsTest extends UnitTestCase {

  /**
   * Revista is the only editorial bundle.
   */
  public function testCanonicalAndLegacyBundlesAreRecognized(): void {
    self::assertSame('revista', RevistaPublications::CANONICAL_BUNDLE);
    self::assertSame(['revista'], RevistaPublications::BUNDLES);
    self::assertTrue(RevistaPublications::isPublication('revista'));
    self::assertFalse(RevistaPublications::isPublication('article'));
    self::assertFalse(RevistaPublications::isPublication('blog'));
    self::assertFalse(RevistaPublications::isPublication('project'));
  }

}
