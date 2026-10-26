<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Account\AccountNames;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects the display-only Growroom author suffix from account nicknames.
 */
#[Group('aculta_user')]
final class NicknameSuffixPolicyTest extends UnitTestCase {

  /**
   * Account nicknames cannot use the reserved Growroom suffix.
   */
  public function testGrowroomSuffixIsReservedForForumAuthorDisplay(): void {
    $this->assertFalse(AccountNames::isValid('maria-gr'));
    $this->assertFalse(AccountNames::isValid('maria-GR'));
    $this->assertNull(AccountNames::publicRouteSlug('maria-gr'));
    $this->assertTrue(AccountNames::isValid('maria-grande'));
  }

}
