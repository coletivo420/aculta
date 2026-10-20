<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects old Magazine updates from recreating Forum contrib structures.
 */
#[Group('aculta_magazine')]
final class LegacyForumUpdatesTest extends UnitTestCase {

  /**
   * Historic update numbers remain available but no longer write Forum config.
   */
  public function testForumTaxonomyUpdatesAreRetired(): void {
    require_once __DIR__ . '/../../../aculta_magazine.install';

    self::assertSame(
      'Update legado aposentado: nenhum campo de categoria do Forum contrib foi criado.',
      \aculta_magazine_update_10001(),
    );
    self::assertSame(
      'Update legado aposentado: nenhuma pasta editorial do Forum contrib foi criada.',
      \aculta_magazine_update_10002(),
    );
  }

}
