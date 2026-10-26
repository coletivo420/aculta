<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Account\AccountNames;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects Growroom author suffixes and canonical nickname slug mapping.
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

  /**
   * A terminal numeric hash suffix maps to a hyphen slug for every save path.
   */
  public function testHashSuffixUsesHyphenatedPublicSlug(): void {
    $this->assertTrue(AccountNames::isValid('nome#2'));
    $this->assertSame('nome-2', AccountNames::publicRouteSlug('nome#2'));
    $this->assertTrue(AccountNames::isValid('nome#10'));
    $this->assertSame('nome-10', AccountNames::publicRouteSlug('nome#10'));
    $this->assertTrue(AccountNames::isValid('nome#1'));
    $this->assertSame('nome-1', AccountNames::publicRouteSlug('nome#1'));
    $this->assertFalse(AccountNames::isValid('nome#texto'));
    $this->assertFalse(AccountNames::isValid('nome-gr#2'));
  }

  /**
   * Automatic collision labels obey the same display-to-route transformation.
   */
  public function testAutomaticSuffixUsesSameCanonicalSlug(): void {
    $nickname = AccountNames::withAutomaticSuffix('nome', 2);
    $this->assertSame('nome#2', $nickname);
    $this->assertSame('nome-2', AccountNames::publicRouteSlug($nickname));
  }

}
