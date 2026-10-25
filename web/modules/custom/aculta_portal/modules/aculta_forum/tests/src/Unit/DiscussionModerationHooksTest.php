<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_forum\Hook\DiscussionModerationHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests access to the moderation rejection marker.
 */
#[Group('aculta_forum')]
final class DiscussionModerationHooksTest extends UnitTestCase {

  /**
   * Ensures only the comment moderation permission grants marker access.
   */
  public function testOnlyCommentModeratorsCanEditOrViewTheRejectionMarker(): void {
    $hook = new DiscussionModerationHooks();
    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('getName')->willReturn('field_aculta_rejected');

    $moderator = $this->createMock(AccountInterface::class);
    $moderator->method('hasPermission')->with('administer comments')->willReturn(TRUE);
    self::assertTrue($hook->entityFieldAccess('edit', $field, $moderator)->isAllowed());

    $participant = $this->createMock(AccountInterface::class);
    $participant->method('hasPermission')->with('administer comments')->willReturn(FALSE);
    self::assertTrue($hook->entityFieldAccess('edit', $field, $participant)->isForbidden());
    self::assertTrue($hook->entityFieldAccess('view', $field, $participant)->isForbidden());
  }

  /**
   * Ensures the hook does not change access to other Comment fields.
   */
  public function testOtherCommentFieldsRemainUnderTheirOwnAccessPolicies(): void {
    $hook = new DiscussionModerationHooks();
    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('getName')->willReturn('comment_body');
    $account = $this->createMock(AccountInterface::class);

    self::assertTrue($hook->entityFieldAccess('edit', $field, $account)->isNeutral());
  }

}
