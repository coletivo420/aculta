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
   * Cria uma conta com as permissões informadas.
   *
   * @param list<string> $permissions
   *   Permissões concedidas à conta.
   */
  private function account(array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(static fn (string $permission): bool => in_array($permission, $permissions, TRUE));
    return $account;
  }

  /**
   * Cria um adaptador que trata apenas o purpose lms.
   *
   * @param bool $supports
   *   Se o adaptador trata o purpose lms.
   * @param bool $allows
   *   Se o adaptador libera o alvo.
   * @param string $permission
   *   Permissão exigida pelo adaptador.
   */
  private function provider(bool $supports, bool $allows, string $permission = 'moderar denuncias lms'): ReportTargetScopeProviderInterface {
    $provider = $this->createMock(ReportTargetScopeProviderInterface::class);
    $provider->method('supports')->willReturnCallback(
      static fn (string $purpose): bool => $supports && $purpose === 'lms',
    );
    $provider->method('allowsTarget')->willReturn($allows);
    $provider->method('permission')->willReturn($permission);
    return $provider;
  }

  /**
   * Cria o escopo com os adaptadores informados.
   *
   * @param list<ReportTargetScopeProviderInterface> $providers
   *   Adaptadores de alvo registrados.
   */
  private function scope(array $providers): ReportTargetScope {
    return new ReportTargetScope($providers, $this->createMock(EntityTypeManagerInterface::class));
  }

  /**
   * Moderador transversal decide sem precisar de adaptador.
   */
  public function testGlobalModeratorDecidesWithoutAdapter(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertTrue($this->scope([])->decide('lms', $target, $this->account(['moderar denuncias cgu'])));
  }

  /**
   * Permissão específica do purpose também decide sem adaptador.
   */
  public function testPurposeSpecificPermissionStillWorks(): void {
    self::assertTrue($this->scope([])->decide('lms', NULL, $this->account(['moderar denuncias cgu: lms'])));
  }

  /**
   * A permissão LMS, sem adaptador registrado, não libera a denúncia (fail closed).
   */
  public function testLmsPermissionAloneDoesNotGrantWithoutAdapter(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertFalse($this->scope([])->decide('lms', $target, $this->account(['moderar denuncias lms'])));
  }

  /**
   * O adaptador libera o alvo quando ele aprova.
   */
  public function testAdapterGrantsWhenTargetIsAllowed(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertTrue($this->scope([$this->provider(TRUE, TRUE)])->decide('lms', $target, $this->account([])));
  }

  /**
   * O adaptador nega o alvo quando ele recusa.
   */
  public function testAdapterDenialIsRespected(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertFalse($this->scope([$this->provider(TRUE, FALSE)])->decide('lms', $target, $this->account([])));
  }

  /**
   * Sem alvo carregado, a decisão por adaptador falha fechada.
   */
  public function testMissingTargetFailsClosed(): void {
    self::assertFalse($this->scope([$this->provider(TRUE, TRUE)])->decide('lms', NULL, $this->account([])));
  }

  /**
   * Adaptador de outro purpose não decide o purpose lms.
   */
  public function testAdapterForAnotherPurposeIsIgnored(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertFalse($this->scope([$this->provider(FALSE, TRUE)])->decide('lms', $target, $this->account([])));
  }

  /**
   * Purpose vazio é negado mesmo para moderador transversal.
   */
  public function testEmptyPurposeIsDenied(): void {
    $target = $this->createMock(EntityInterface::class);
    self::assertFalse($this->scope([])->decide('', $target, $this->account(['moderar denuncias cgu'])));
  }

  /**
   * O purpose escopado só aparece para quem tem a permissão do adaptador.
   */
  public function testScopedPurposeListedOnlyWithAdapterPermission(): void {
    $scope = $this->scope([$this->provider(TRUE, TRUE)]);
    self::assertSame(['lms'], $scope->scopedPurposesFor($this->account(['moderar denuncias lms'])));
    self::assertSame([], $scope->scopedPurposesFor($this->account([])));
  }

}
