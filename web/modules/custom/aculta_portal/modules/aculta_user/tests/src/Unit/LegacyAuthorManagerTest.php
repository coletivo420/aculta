<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Legacy\LegacyAuthorManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Formato da origem das autorias históricas do Fórum (ACULTA Fórum, F2).
 */
#[Group('aculta_user')]
class LegacyAuthorManagerTest extends UnitTestCase {

  public static function origensValidas(): array {
    return [['12345'], ['a.b-c_9'], [str_repeat('x', 64)]];
  }

  public static function origensInvalidas(): array {
    return [[''], ['com espaco'], ['../etc'], ['acento-ç'], [str_repeat('x', 65)]];
  }

  #[DataProvider('origensValidas')]
  public function testOrigemValida(string $origem): void {
    $this->assertTrue(LegacyAuthorManager::isValidSourceId($origem));
  }

  #[DataProvider('origensInvalidas')]
  public function testOrigemInvalida(string $origem): void {
    $this->assertFalse(LegacyAuthorManager::isValidSourceId($origem));
  }

}
