<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform\WebformSubmissionInterface;

require_once dirname(__DIR__, 8) . '/modules/contrib/webform_score/webform_score.module';

/** @coversFunction webform_score_entity_field_access */
final class WebformScoreAccessTest extends UnitTestCase {

  public function testOnlySubmissionOwnerCanViewOwnScoreWhenGranted(): void {
    [$field, $items, $account] = $this->scoreField(TRUE, ['view own submission score']);

    $result = webform_score_entity_field_access('view', $field, $account, $items);

    self::assertTrue($result->isNeutral());
    self::assertContains('user', $result->getCacheContexts());
  }

  public function testOtherUserCannotViewOwnScore(): void {
    [$field, $items, $account] = $this->scoreField(FALSE, ['view own submission score']);

    $result = webform_score_entity_field_access('view', $field, $account, $items);

    self::assertTrue($result->isForbidden());
    self::assertContains('user', $result->getCacheContexts());
  }

  public function testDedicatedPermissionCanViewAnyScore(): void {
    [$field, $items, $account] = $this->scoreField(FALSE, ['view any submission score']);

    self::assertTrue(webform_score_entity_field_access('view', $field, $account, $items)->isNeutral());
  }

  public function testScoreCannotBeEditedDirectly(): void {
    [$field, $items, $account] = $this->scoreField(TRUE, ['view own submission score']);

    self::assertTrue(webform_score_entity_field_access('edit', $field, $account, $items)->isForbidden());
  }

  /**
   * Creates a mock score field on an owned or unowned submission.
   *
   * @param bool $is_owner
   *   Whether the submission belongs to the account.
   * @param string[] $permissions
   *   Permissions granted to the account.
   *
   * @return array{0: \Drupal\Core\Field\FieldDefinitionInterface, 1: \Drupal\Core\Field\FieldItemListInterface, 2: \Drupal\Core\Session\AccountInterface}
   */
  private function scoreField(bool $is_owner, array $permissions): array {
    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('getName')->willReturn('webform_score');

    $submission = $this->createMock(WebformSubmissionInterface::class);
    $submission->method('isOwner')->willReturn($is_owner);
    $submission->method('getCacheContexts')->willReturn([]);
    $submission->method('getCacheTags')->willReturn([]);
    $submission->method('getCacheMaxAge')->willReturn(-1);
    $item = (object) ['denominator' => 4];
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('offsetExists')->with(0)->willReturn(TRUE);
    $items->method('offsetGet')->with(0)->willReturn($item);
    $items->method('getEntity')->willReturn($submission);

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(static fn (string $permission): bool => in_array($permission, $permissions, TRUE));

    return [$field, $items, $account];
  }

}
