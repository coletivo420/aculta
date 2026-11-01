<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Domain;

use Drupal\aculta_portal\Domain\FrontPathPolicy;
use Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Acesso direto ao caminho interno da raiz responde 404.
 */
#[Group('aculta_portal')]
final class FrontPathPolicyTest extends UnitTestCase {

  /**
   * Provides internal purpose paths.
   */
  public static function caminhosInternos(): array {
    $casos = [];
    foreach (PurposeFrontPathProcessor::FRONT_PATHS as $purpose => $path) {
      if ($purpose === 'account') {
        continue;
      }
      $casos[$purpose] = [$purpose, $path];
    }
    return $casos;
  }

  /**
   * Caminhos internos diretos respondem 404.
   */
  #[DataProvider('caminhosInternos')]
  public function testCaminhoInternoDiretoEhBloqueado(string $purpose, string $path): void {
    $this->assertTrue(FrontPathPolicy::bloqueiaCaminhoInterno($purpose, $path, FALSE));
  }

  /**
   * A raiz reescrita pelo processador passa pela política.
   */
  #[DataProvider('caminhosInternos')]
  public function testRaizServidaPeloProcessadorNaoEhBloqueada(string $purpose, string $path): void {
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno($purpose, $path, TRUE));
  }

  /**
   * Caminhos não relacionados continuam acessíveis.
   */
  public function testOutrosCaminhosNaoSaoBloqueados(): void {
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('support', '/', FALSE));
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('support', '/contato', FALSE));
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('wiki', '/apoio', FALSE));
  }

  /**
   * The Conta entry path is public directly as well as at the ACCOUNT root.
   */
  public function testAccountEntryPathRemainsPublic(): void {
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('account', '/conta', FALSE));
  }

  /**
   * O índice antigo do Forum permanece fechado após a migração da raiz.
   */
  public function testLegacyForumIndexPathRemainsClosed(): void {
    $this->assertTrue(FrontPathPolicy::bloqueiaCaminhoInterno('forum', '/forum', FALSE));
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('forum', '/forum', TRUE));
  }

  /**
   * Purposes sem caminho interno não adicionam bloqueios.
   */
  public function testPurposeSemRaizInternaNaoBloqueia(): void {
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno('main', '/apoio', FALSE));
    $this->assertFalse(FrontPathPolicy::bloqueiaCaminhoInterno(NULL, '/apoio', FALSE));
  }

  /**
   * Canonical Studio areas are allowed only under their owning purpose.
   */
  public function testCanonicalStudioPathsArePurposeScoped(): void {
    $this->assertTrue(FrontPathPolicy::caminhoStudioCanonicoPermitido('shop', '/studio/green-stash'));
    $this->assertTrue(FrontPathPolicy::caminhoStudioCanonicoPermitido(
      'shop', '/studio/green-stash/produtos/novo',
    ));
    $this->assertTrue(FrontPathPolicy::caminhoStudioCanonicoPermitido('wiki', '/studio/we-sativa'));
    $this->assertTrue(FrontPathPolicy::caminhoStudioCanonicoPermitido(
      'wiki', '/studio/we-sativa/propostas/17/decidir',
    ));

    $this->assertFalse(FrontPathPolicy::caminhoStudioCanonicoPermitido('account', '/studio/green-stash'));
    $this->assertFalse(FrontPathPolicy::caminhoStudioCanonicoPermitido('wiki', '/studio/green-stash'));
    $this->assertFalse(FrontPathPolicy::caminhoStudioCanonicoPermitido('shop', '/studio/we-sativa'));
    $this->assertFalse(FrontPathPolicy::caminhoStudioCanonicoPermitido('account', '/studio/marley'));
    $this->assertFalse(FrontPathPolicy::caminhoStudioCanonicoPermitido('wiki', '/studio/we-sativa-legacy'));
  }

}
