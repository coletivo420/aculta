<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regras de visibilidade do perfil público (plano aculta_user, seção B2).
 *
 */
#[Group('aculta_user')]
class PublicProfilePolicyTest extends UnitTestCase {

  /**
   * Perfil com todos os itens ligados, para os cenários de exclusão.
   */
  private function perfilCompleto(array $override = []): array {
    return $override + [
      'publico' => TRUE,
      'apelido' => 'maria-souza',
      'exibir_bio' => TRUE,
      'exibir_email' => TRUE,
      'exibir_redes' => TRUE,
    ];
  }

  /**
   * Perfil público mostra apelido e foto; bio, e-mail e redes dependem da escolha da pessoa.
   */
  public function testPadraoMostraApelidoEFoto(): void {
    $policy = new PublicProfilePolicy();
    $perfil = ['publico' => TRUE, 'apelido' => 'maria-souza', 'exibir_bio' => FALSE, 'exibir_email' => FALSE, 'exibir_redes' => FALSE];
    $this->assertSame(['apelido' => TRUE, 'foto' => TRUE], $policy->publicItems($perfil));
  }

  /**
   * Perfil privado mostra somente foto e apelido; bio, redes e e-mail somem.
   */
  public function testPerfilPrivadoMostraSoFotoEApelido(): void {
    $policy = new PublicProfilePolicy();
    $this->assertSame(['apelido' => TRUE, 'foto' => TRUE], $policy->publicItems($this->perfilCompleto(['publico' => FALSE])));
  }

  /**
   * Cada item aparece só quando a pessoa escolhe exibi-lo.
   */
  public function testCadaItemDependeDaEscolha(): void {
    $policy = new PublicProfilePolicy();
    $items = $policy->publicItems($this->perfilCompleto());
    $this->assertSame(['apelido' => TRUE, 'foto' => TRUE, 'bio' => TRUE, 'email' => TRUE, 'redes' => TRUE], $items);

    $items = $policy->publicItems($this->perfilCompleto(['exibir_email' => FALSE]));
    $this->assertArrayNotHasKey('email', $items);
    $this->assertArrayHasKey('bio', $items);
  }

  /**
   * Sem apelido, nada é público, mesmo com o perfil marcado como público.
   */
  public function testSemApelidoNaoHaPerfil(): void {
    $policy = new PublicProfilePolicy();
    $this->assertSame([], $policy->publicItems($this->perfilCompleto(['apelido' => '  '])));
  }

  /**
   * A política nunca devolve cidade, UF, WhatsApp, nome ou endereço: chaves fora da lista.
   */
  public function testSomenteItensPermitidos(): void {
    $policy = new PublicProfilePolicy();
    $items = $policy->publicItems($this->perfilCompleto(['cidade' => 'Campinas', 'uf' => 'SP', 'whatsapp' => '1199']));
    $this->assertSame([], array_diff(array_keys($items), PublicProfilePolicy::ITEMS));
  }

}
