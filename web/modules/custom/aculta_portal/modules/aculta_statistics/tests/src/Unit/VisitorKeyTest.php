<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Visitor\VisitorKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Identificador pseudonimo de visitante.
 */
#[CoversClass(VisitorKey::class)]
#[Group('aculta_statistics')]
final class VisitorKeyTest extends TestCase {

  /**
   * A chave não contém o IP em claro e é estável para o mesmo visitante.
   */
  public function testKeyIsStableAndHidesIp(): void {
    $key = new VisitorKey('salt');

    $identifier = $key->identifier('203.0.113.7', 'client');

    $this->assertSame($identifier, $key->identifier('203.0.113.7', 'client'));
    $this->assertStringNotContainsString('203.0.113.7', $identifier);
  }

  /**
   * Escopos diferentes geram chaves diferentes para o mesmo visitante.
   */
  public function testScopesProduceDifferentKeys(): void {
    $key = new VisitorKey('salt');

    $this->assertNotSame($key->identifier('203.0.113.7', 'client'), $key->identifier('203.0.113.7', 'node:42'));
  }

  /**
   * Salts diferentes não geram a mesma chave, então a chave não é reversível sem o salt do site.
   */
  public function testDifferentSaltProducesDifferentKey(): void {
    $this->assertNotSame(
      (new VisitorKey('salt-a'))->identifier('203.0.113.7', 'client'),
      (new VisitorKey('salt-b'))->identifier('203.0.113.7', 'client'),
    );
  }

}
