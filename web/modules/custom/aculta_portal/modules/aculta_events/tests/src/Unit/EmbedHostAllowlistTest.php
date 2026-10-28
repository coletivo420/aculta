<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\Embed\EmbedHostAllowlist;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Hosts autorizados a incorporar a agenda, vindos do mapeamento do aculta_deployer.
 */
#[Group('aculta_events')]
class EmbedHostAllowlistTest extends UnitTestCase {

  /**
   * Teste e produção entram; duplicados saem; blocos sem host são ignorados.
   */
  public function testColetaTesteEProducaoSemRepetir(): void {
    $mapeamento = [
      'domain_transition' => ['entries' => [
        ['domain_id' => 'a', 'test_host' => 'a.teste.invalid', 'production_host' => 'a.prod.invalid'],
        ['domain_id' => 'b', 'test_host' => 'b.teste.invalid', 'production_host' => 'a.prod.invalid'],
        ['domain_id' => 'c'],
      ]],
    ];
    $this->assertSame(
      ['a.prod.invalid', 'a.teste.invalid', 'b.teste.invalid'],
      EmbedHostAllowlist::hostsDoMapeamento($mapeamento),
    );
  }

  /**
   * O mapeamento real do repositório traz os dois ambientes de todos os domínios (10 × 2).
   */
  public function testMapeamentoRealTemDezDominiosEmDoisAmbientes(): void {
    $caminho = dirname(__DIR__, 4) . '/aculta_deployer/config/deploy.json';
    $dados = json_decode((string) file_get_contents($caminho), TRUE, 512, JSON_THROW_ON_ERROR);
    $hosts = EmbedHostAllowlist::hostsDoMapeamento($dados);
    $this->assertCount(20, $hosts);
  }

}
