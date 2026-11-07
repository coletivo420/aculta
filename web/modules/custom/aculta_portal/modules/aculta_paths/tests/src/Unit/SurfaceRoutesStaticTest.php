<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_paths\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Teste estático das rotas da fase 3.0 (lê os YAML, não sobe o Drupal).
 *
 * Confere os caminhos novos de WE SATIVA, GREEN STASH e Wiki, e que os caminhos
 * antigos não são mais declarados. O teste Kernel com HTTP real fica DEFERRED
 * para o Runtime.
 */
#[Group('aculta_paths')]
final class SurfaceRoutesStaticTest extends UnitTestCase {

  /**
   * Rotas declaradas nos YAML dos módulos envolvidos.
   *
   * @return array<string, array<string, mixed>>
   *   Rotas indexadas pelo nome, conforme os arquivos YAML.
   */
  private static function rotas(): array {
    $modulos = dirname(__DIR__, 4);
    $rotas = [];
    foreach (['aculta_wiki/aculta_wiki.routing.yml', 'aculta_studio/aculta_studio.routing.yml', 'aculta_commerce/aculta_commerce.routing.yml'] as $arquivo) {
      $dados = Yaml::parseFile($modulos . '/' . $arquivo);
      $rotas = array_merge($rotas, is_array($dados) ? $dados : []);
    }
    return $rotas;
  }

  /**
   * WE SATIVA em /we-sativa, no purpose wiki.
   */
  public function testWeSativaEmCaminhoProprio(): void {
    $rotas = self::rotas();
    $this->assertSame('/we-sativa', $rotas['aculta_studio.we_sativa']['path']);
    $this->assertSame('wiki', $rotas['aculta_studio.we_sativa']['options']['_aculta_domain_purpose']);
    $this->assertSame('/we-sativa/inicio', $rotas['aculta_studio.we_sativa_start']['path']);
  }

  /**
   * GREEN STASH em /green-stash, no purpose shop.
   */
  public function testGreenStashEmCaminhoProprio(): void {
    $rotas = self::rotas();
    $this->assertSame('/green-stash', $rotas['aculta_commerce.green_stash']['path']);
    $this->assertSame('shop', $rotas['aculta_commerce.green_stash']['options']['_aculta_domain_purpose']);
  }

  /**
   * Ação de decisão da WE SATIVA migrou para /we-sativa.
   */
  public function testAcaoDeDecisaoDaWeSativa(): void {
    $this->assertSame('/we-sativa/propostas/{proposal}/decidir', self::rotas()['aculta_wiki.proposal_decide']['path']);
  }

  /**
   * Formulário de caminhos fica em /painel-administrativo/aculta/caminhos, só no MAIN.
   */
  public function testFormularioEhSoMain(): void {
    $dados = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_paths.routing.yml');
    $rota = $dados['aculta_paths.form'];
    $this->assertSame('/painel-administrativo/aculta/caminhos', $rota['path']);
    $this->assertSame('main', $rota['options']['_aculta_domain_purpose']);
    $this->assertSame('administrar superficies', $rota['requirements']['_permission']);
  }

  /**
   * A edição abre em modal dentro do MAIN, com o purpose na rota.
   */
  public function testEdicaoEhSoMainEModal(): void {
    $dados = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_paths.routing.yml');
    $rota = $dados['aculta_paths.editar'];
    $this->assertSame('/painel-administrativo/aculta/caminhos/{purpose}/editar', $rota['path']);
    $this->assertSame('main', $rota['options']['_aculta_domain_purpose']);
    $this->assertSame('administrar superficies', $rota['requirements']['_permission']);
  }

  /**
   * Nenhum caminho antigo ou /studio continua declarado.
   */
  public function testCaminhosAntigosNaoDeclarados(): void {
    $proibidos = ['/studio', '/wesativa', '/greenstash', '/wiki/busca', '/wiki/verbetes', '/wiki/propostas'];
    foreach (self::rotas() as $nome => $rota) {
      $caminho = (string) ($rota['path'] ?? '');
      foreach ($proibidos as $proibido) {
        $this->assertFalse(
          $caminho === $proibido || str_starts_with($caminho, $proibido . '/'),
          sprintf('%s declara o caminho antigo %s', $nome, $caminho),
        );
      }
    }
  }

}
