<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Domain;

use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regras de validação da configuração de superfícies (Fase 3.4 e 3.5).
 */
#[Group('aculta_portal')]
final class SurfaceConfigReaderTest extends UnitTestCase {

  /**
   * Superfícies em subdomínio, ativas, sem slug.
   */
  private static function padrao(): array {
    $saida = [];
    foreach (SurfaceConfigReader::TITULOS as $purpose => $titulo) {
      $saida[$purpose] = ['modo' => 'subdominio', 'ativa' => TRUE, 'slug' => '', 'titulo' => $titulo];
    }
    return $saida;
  }

  /**
   * Configuração padrão é válida.
   */
  public function testPadraoEhValido(): void {
    $this->assertSame([], SurfaceConfigReader::validar(self::padrao()));
  }

  /**
   * MAIN não pode ser desativado.
   */
  public function testMainNaoPodeSerDesativado(): void {
    $s = self::padrao();
    $s['main']['ativa'] = FALSE;
    $this->assertArrayHasKey('main', SurfaceConfigReader::validar($s));
  }

  /**
   * MAIN não pode ir para diretório.
   */
  public function testMainNaoPodeIrParaDiretorio(): void {
    $s = self::padrao();
    $s['main'] = ['modo' => 'diretorio', 'ativa' => TRUE, 'slug' => 'inicio', 'titulo' => 'Principal'];
    $this->assertArrayHasKey('main', SurfaceConfigReader::validar($s));
  }

  /**
   * Slug de diretório segue o padrão de minúsculas e hífen.
   */
  public function testSlugInvalidoEhRecusado(): void {
    foreach (['Eventos', 'eventos_x', 'ev entos', '-x', 'x-'] as $slug) {
      $s = self::padrao();
      $s['eventos'] = ['modo' => 'diretorio', 'ativa' => TRUE, 'slug' => $slug, 'titulo' => 'Eventos'];
      $this->assertArrayHasKey('eventos', SurfaceConfigReader::validar($s), $slug);
    }
  }

  /**
   * Slug válido em diretório passa.
   */
  public function testSlugValidoPassa(): void {
    $s = self::padrao();
    $s['eventos'] = ['modo' => 'diretorio', 'ativa' => TRUE, 'slug' => 'eventos-2', 'titulo' => 'Eventos'];
    $this->assertSame([], SurfaceConfigReader::validar($s));
  }

  /**
   * Slugs reservados são recusados.
   */
  public function testSlugReservadoEhRecusado(): void {
    $s = self::padrao();
    $s['wiki'] = ['modo' => 'diretorio', 'ativa' => TRUE, 'slug' => 'studio', 'titulo' => 'Wiki'];
    $this->assertArrayHasKey('wiki', SurfaceConfigReader::validar($s));
  }

  /**
   * Dois purposes não podem usar o mesmo slug.
   */
  public function testSlugDuplicadoEhRecusado(): void {
    $s = self::padrao();
    $s['wiki'] = ['modo' => 'diretorio', 'ativa' => TRUE, 'slug' => 'sativa', 'titulo' => 'Wiki'];
    $s['shop'] = ['modo' => 'diretorio', 'ativa' => TRUE, 'slug' => 'sativa', 'titulo' => 'Loja'];
    $erros = SurfaceConfigReader::validar($s);
    $this->assertArrayHasKey('shop', $erros);
    $this->assertArrayNotHasKey('wiki', $erros);
  }

  /**
   * Só as nove superfícies permitidas podem ir para diretório.
   */
  public function testDiretorioSoParaSuperficiesPermitidas(): void {
    $permitidas = SurfaceConfigReader::DIRETORIO_PERMITIDO;
    $this->assertCount(9, $permitidas);
    $this->assertNotContains('main', $permitidas);
    foreach ($permitidas as $purpose) {
      $s = self::padrao();
      $s[$purpose] = ['modo' => 'diretorio', 'ativa' => TRUE, 'slug' => 'ok-' . $purpose, 'titulo' => 'X'];
      $this->assertSame([], SurfaceConfigReader::validar($s), $purpose);
    }
  }

}
