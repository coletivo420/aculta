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
    foreach ([
      'aculta_wiki/aculta_wiki.routing.yml',
      'aculta_studio/aculta_studio.routing.yml',
      'aculta_commerce/aculta_commerce.routing.yml',
    ] as $arquivo) {
      $dados = Yaml::parseFile($modulos . '/' . $arquivo);
      if (is_array($dados)) {
        foreach ($dados as $key => $item) {
          if (is_string($key) && is_array($item)) {
            /** @var array<string, mixed> $item */
            $rotas[$key] = $item;
          }
        }
      }
    }
    return $rotas;
  }

  /**
   * WE SATIVA em /we-sativa, na site_key wiki.
   */
  public function testWeSativaEmCaminhoProprio(): void {
    $rotas = self::rotas();
    $this->assertArrayHasKey('aculta_studio.we_sativa', $rotas);
    $this->assertIsArray($rotas['aculta_studio.we_sativa']['options'] ?? NULL);
    $this->assertSame('/we-sativa', $rotas['aculta_studio.we_sativa']['path']);
    $this->assertSame('wiki', $rotas['aculta_studio.we_sativa']['options']['_aculta_site_key']);
    $this->assertSame('/we-sativa/inicio', $rotas['aculta_studio.we_sativa_start']['path']);
  }

  /**
   * GREEN STASH em /green-stash, na site_key shop.
   */
  public function testGreenStashEmCaminhoProprio(): void {
    $rotas = self::rotas();
    $this->assertArrayHasKey('aculta_commerce.green_stash', $rotas);
    $this->assertIsArray($rotas['aculta_commerce.green_stash']['options'] ?? NULL);
    $this->assertSame('/green-stash', $rotas['aculta_commerce.green_stash']['path']);
    $this->assertSame('shop', $rotas['aculta_commerce.green_stash']['options']['_aculta_site_key']);
  }

  /**
   * Ação de decisão da WE SATIVA migrou para /we-sativa.
   */
  public function testAcaoDeDecisaoDaWeSativa(): void {
    $rotas = self::rotas();
    $this->assertArrayHasKey('aculta_wiki.proposal_decide', $rotas);
    $this->assertSame(
      '/we-sativa/propostas/{proposal}/decidir',
      $rotas['aculta_wiki.proposal_decide']['path'],
    );
  }

  /**
   * O formulário de caminhos fica restrito ao MAIN.
   */
  public function testFormularioEhSoMain(): void {
    $dados = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_paths.routing.yml');
    $this->assertIsArray($dados);
    $this->assertIsArray($dados['aculta_paths.form'] ?? NULL);
    $rota = $dados['aculta_paths.form'];
    $this->assertIsArray($rota['options'] ?? NULL);
    $this->assertIsArray($rota['requirements'] ?? NULL);
    $this->assertSame('/painel-administrativo/aculta/caminhos', $rota['path']);
    $this->assertSame('main', $rota['options']['_aculta_site_key']);
    $this->assertSame('administrar superficies', $rota['requirements']['_permission']);
  }

  /**
   * A edição abre em modal dentro do MAIN, com a site_key na rota.
   */
  public function testEdicaoEhSomenteMainComModal(): void {
    $dados = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_paths.routing.yml');
    $this->assertIsArray($dados);
    $this->assertIsArray($dados['aculta_paths.editar'] ?? NULL);
    $rota = $dados['aculta_paths.editar'];
    $this->assertIsArray($rota['options'] ?? NULL);
    $this->assertIsArray($rota['requirements'] ?? NULL);
    $this->assertSame('/painel-administrativo/aculta/caminhos/{site_key}/editar', $rota['path']);
    $this->assertSame('main', $rota['options']['_aculta_site_key']);
    $this->assertSame('administrar superficies', $rota['requirements']['_permission']);
  }

  /**
   * Nenhum caminho antigo ou /studio continua declarado.
   */
  public function testCaminhosAntigosNaoDeclarados(): void {
    $proibidos = ['/studio', '/wesativa', '/greenstash', '/wiki/busca', '/wiki/verbetes', '/wiki/propostas'];
    foreach (self::rotas() as $nome => $rota) {
      $pathRaw = $rota['path'] ?? '';
      $caminho = is_string($pathRaw) ? $pathRaw : '';
      foreach ($proibidos as $proibido) {
        $this->assertFalse(
          $caminho === $proibido || str_starts_with($caminho, $proibido . '/'),
          sprintf('%s declara o caminho antigo %s', $nome, $caminho),
        );
      }
    }
  }

}
