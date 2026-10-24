<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Form\NewConversationForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests canonicalization of untrusted group-conversation recipients.
 */
#[CoversClass(NewConversationForm::class)]
#[Group('aculta_messenger')]
final class NewConversationRecipientIdsTest extends UnitTestCase {

  /**
   * Duplicate and malformed submitted IDs cannot repeat or alter recipients.
   */
  public function testRecipientIdsAreUniquePositiveIntegers(): void {
    self::assertSame(
      [42, 84, 105],
      NewConversationForm::normalizeRecipientIds([
        '42',
        84,
        '42',
        '105',
        '84',
        0,
        -1,
        '042',
        '84garbage',
        TRUE,
        12.5,
        ['id' => 7],
      ]),
    );
  }

  /**
   * Non-array form input produces no recipients.
   */
  public function testNonArrayInputProducesNoRecipients(): void {
    self::assertSame([], NewConversationForm::normalizeRecipientIds('42'));
    self::assertSame([], NewConversationForm::normalizeRecipientIds(NULL));
  }

}
