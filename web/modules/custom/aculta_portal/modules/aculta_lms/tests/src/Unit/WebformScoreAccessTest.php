<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform\WebformSubmissionInterface;
use PHPUnit\Framework\Attributes\CoversFunction;

require_once dirname(__DIR__, 8) . '/modules/contrib/webform_score/webform_score.module';

/**
 * Testa o acesso aos campos de nota do módulo webform_score.
 */
#[CoversFunction('webform_score_entity_field_access')]
final class WebformScoreAccessTest extends UnitTestCase {

  /**
   * Permite que a pessoa dona veja a própria nota quando tem permissão.
   */
  public function testOnlySubmissionOwnerCanViewOwnScoreWhenGranted(): void {
    [$field, $items, $account] = $this->scoreField(TRUE, ['view own submission score']);

    $result = webform_score_entity_field_access('view', $field, $account, $items);

    self::assertInstanceOf(AccessResultInterface::class, $result);
    self::assertTrue($result->isNeutral());
    self::assertInstanceOf(CacheableDependencyInterface::class, $result);
    self::assertContains('user', $result->getCacheContexts());
  }

  /**
   * Impede que outra pessoa veja a nota como se fosse a própria.
   */
  public function testOtherUserCannotViewOwnScore(): void {
    [$field, $items, $account] = $this->scoreField(FALSE, ['view own submission score']);

    $result = webform_score_entity_field_access('view', $field, $account, $items);

    self::assertInstanceOf(AccessResultInterface::class, $result);
    self::assertTrue($result->isForbidden());
    self::assertInstanceOf(CacheableDependencyInterface::class, $result);
    self::assertContains('user', $result->getCacheContexts());
  }

  /**
   * Permite ver qualquer nota com a permissão dedicada.
   */
  public function testDedicatedPermissionCanViewAnyScore(): void {
    [$field, $items, $account] = $this->scoreField(FALSE, ['view any submission score']);

    $result = webform_score_entity_field_access('view', $field, $account, $items);
    self::assertInstanceOf(AccessResultInterface::class, $result);
    self::assertTrue($result->isNeutral());
  }

  /**
   * Impede a edição direta da nota.
   */
  public function testScoreCannotBeEditedDirectly(): void {
    [$field, $items, $account] = $this->scoreField(TRUE, ['view own submission score']);

    $result = webform_score_entity_field_access('edit', $field, $account, $items);
    self::assertInstanceOf(AccessResultInterface::class, $result);
    self::assertTrue($result->isForbidden());
  }

  /**
   * Creates a mock score field on an owned or unowned submission.
   *
   * @param bool $is_owner
   *   Whether the submission belongs to the account.
   * @param string[] $permissions
   *   Permissions granted to the account.
   *
   * @return array{\Drupal\Core\Field\FieldDefinitionInterface, \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>&\PHPUnit\Framework\MockObject\MockObject, \Drupal\Core\Session\AccountInterface}
   *   Campo, lista de itens e conta simulados.
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
