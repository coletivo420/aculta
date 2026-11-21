<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Unit;

use Drupal\aculta_cgu\Scope\ReportTargetScope;
use Drupal\aculta_cgu\Scope\ReportTargetScopeProviderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Decisão por alvo: moderador global, adaptador de purpose restrito e fail closed.
 */
#[Group('aculta_cgu')]
final class ReportTargetScopeTest extends UnitTestCase {

  /**
   * @param list<string> $permissions
   */
  private function account(array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(static fn (string $permission): bool => in_array($permission, $permissions, TRUE));
    return $account;
  }

  private function provider(bool $supports, bool $allows): ReportTargetScopeProviderInterface {
    $provider = $this->createMock(ReportTargetScopeProviderInterface::class);
    $provider->method('supports')->willReturn($supports);
    $provider->method('allowsTarget')->willReturn($allows);
    return $provider;
  }

  /**
   * @param list<ReportTargetScopeProviderInterface> $providers
   */
  private function scope(array $providers): ReportTargetScope {
    return new ReportTargetScope($providers, $this->createMock(EntityTypeManagerInterface::class));
  }

  public function testGlobalModeratorDecidesWithoutAdapter(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertTrue($this->scope([])->decide('lms', $target, $this->account(['moderar denuncias cgu'])));
  }

  public function testPurposeSpecificPermissionStillWorks(): void {
    self::assertTrue($this->scope([])->decide('lms', NULL, $this->account(['moderar denuncias cgu: lms'])));
  }

  public function testLmsPermissionAloneDoesNotGrantWithoutAdapter(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertFalse($this->scope([])->decide('lms', $target, $this->account(['moderar denuncias lms'])));
  }

  public function testAdapterGrantsWhenTargetIsAllowed(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertTrue($this->scope([$this->provider(TRUE, TRUE)])->decide('lms', $target, $this->account([])));
  }

  public function testAdapterDenialIsRespected(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertFalse($this->scope([$this->provider(TRUE, FALSE)])->decide('lms', $target, $this->account([])));
  }

  public function testMissingTargetFailsClosed(): void {
    self::assertFalse($this->scope([$this->provider(TRUE, TRUE)])->decide('lms', NULL, $this->account([])));
  }

  public function testAdapterForAnotherPurposeIsIgnored(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertFalse($this->scope([$this->provider(FALSE, TRUE)])->decide('lms', $target, $this->account([])));
  }

  public function testEmptyPurposeIsDenied(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertFalse($this->scope([])->decide('', $target, $this->account(['moderar denuncias cgu'])));
  }

}
