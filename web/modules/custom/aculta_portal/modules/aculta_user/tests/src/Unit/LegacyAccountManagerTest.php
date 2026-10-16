<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Legacy\LegacyAccountManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Formato da origem e nome técnico das contas legadas (ACULTA Fórum, F2).
 */
#[Group('aculta_user')]
class LegacyAccountManagerTest extends UnitTestCase {

  public static function origensValidas(): array {
    return [['12345'], ['a.b-c_9'], [str_repeat('x', 64)]];
  }

  public static function origensInvalidas(): array {
    return [[''], ['com espaco'], ['../etc'], ['acento-ç'], [str_repeat('x', 65)]];
  }

  #[DataProvider('origensValidas')]
  public function testOrigemValida(string $origem): void {
    $this->assertTrue(LegacyAccountManager::isValidSourceId($origem));
  }

  #[DataProvider('origensInvalidas')]
  public function testOrigemInvalida(string $origem): void {
    $this->assertFalse(LegacyAccountManager::isValidSourceId($origem));
  }

  /**
   * O nome técnico é estável, começa pelo prefixo e não contém pontuação.
   */
  public function testNomeTecnicoEstavel(): void {
    $this->assertSame('legado-12345', LegacyAccountManager::usernameFor('12345'));
    $this->assertSame('legado-ab9', LegacyAccountManager::usernameFor('a.b-9'));
    $this->assertSame(LegacyAccountManager::usernameFor('a.b-9'), LegacyAccountManager::usernameFor('a.b-9'));
  }

  /**
   * O nome técnico nunca passa de 48 caracteres e não colide com o apelido reservado do memorial.
   */
  public function testNomeTecnicoLimitado(): void {
    $nome = LegacyAccountManager::usernameFor(str_repeat('9', 64));
    $this->assertLessThanOrEqual(48, strlen($nome));
    $this->assertStringStartsWith(LegacyAccountManager::USERNAME_PREFIX, $nome);
    $this->assertNotSame('lendashistoricas', $nome);
  }

}
