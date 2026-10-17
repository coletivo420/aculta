<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Domain;

use Drupal\aculta_portal\Domain\FrontPathPolicy;
use Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Caminho interno da raiz de cada subdomínio responde 404 em acesso direto (regra uniforme).
 */
#[Group('aculta_portal')]
final class FrontPathPolicyTest extends UnitTestCase {

  public static function caminhosInternos(): array {
    $casos = [];
    foreach (PurposeFrontPathProcessor::FRONT_PATHS as $purpose => $path) {
      $casos[$purpose] = [$purpose, $path];
    }
    return $casos;
  }

  #[DataProvider('caminhosInternos')]
  public function testCaminhoInternoDiretoEhBloqueado(string $purpose, string $path): void {
    $this->assertTrue(FrontPathPolicy::bloqueiaCaminhoInterno($purpose, $path, FALSE));
  }

  #[DataProvider('caminhosInternos')]
  public function testRaizServidaPeloProcessadorNaoEhBloqueada(string $purpose, string $path): void {
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno($purpose, $path, TRUE));
  }

  public function testOutrosCaminhosNaoSaoBloqueados(): void {
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('support', '/', FALSE));
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('support', '/contato', FALSE));
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('wiki', '/apoio', FALSE));
  }

  public function testPurposeSemRaizInternaNaoBloqueia(): void {
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('main', '/apoio', FALSE));
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno(NULL, '/apoio', FALSE));
  }

}
