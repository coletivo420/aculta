<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Role;

use Drupal\aculta_user\Role\HerancaDePapeis;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Herança de papéis do aculta_user: mapa a partir da cadeia, herdados e papéis não gravados.
 */
#[Group('aculta_user')]
final class HerancaDePapeisTest extends UnitTestCase {

  private const TODOS = ['anonymous', 'authenticated', 'email_confirmed', 'estudante', 'associado', 'lms_teacher', 'content_editor', 'gerente_loja', 'administrator', 'wiki_contributor'];

  private function servico(array $cadeia, array $laterais, array $gravadosNoBanco = []): HerancaDePapeis {
    $configuracao = $this->createMock(Config::class);
    $configuracao->method('get')->willReturnCallback(fn (string $chave) => match ($chave) {
      'cadeia' => $cadeia,
      'laterais' => $laterais,
      default => NULL,
    });
    $fabrica = $this->createMock(ConfigFactoryInterface::class);
    $fabrica->method('get')->willReturn($configuracao);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn(array_combine(self::TODOS, array_map(fn () => $this->createMock(\stdClass::class), self::TODOS)));
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->willReturn($storage);

    $consulta = $this->createMock(SelectInterface::class);
    $consulta->method('fields')->willReturnSelf();
    $consulta->method('condition')->willReturnSelf();
    $resultado = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $resultado->method('fetchCol')->willReturn($gravadosNoBanco);
    $consulta->method('execute')->willReturn($resultado);
    $banco = $this->createMock(Connection::class);
    $banco->method('select')->willReturn($consulta);

    return new HerancaDePapeis($fabrica, $etm, $banco);
  }

  private const CADEIA = ['gerente_loja', 'content_editor', 'lms_teacher', 'associado', 'estudante', 'email_confirmed', 'authenticated'];

  public function testMapaSegueACadeiaELaterais(): void {
    $mapa = $this->servico(self::CADEIA, ['wiki_contributor' => 'email_confirmed'])->mapa();
    $this->assertSame(['content_editor'], $mapa['gerente_loja']);
    $this->assertSame(['email_confirmed'], $mapa['wiki_contributor']);
    $this->assertSame(['authenticated'], $mapa['email_confirmed']);
  }

  public function testHerdadosSaoTransitivosSemOsBasicos(): void {
    $herdados = $this->servico(self::CADEIA, [])->herdados(['lms_teacher']);
    $this->assertSame(['associado', 'estudante', 'email_confirmed'], $herdados);
  }

  public function testPapelNovoSemCategoriaHerdaDoAutenticado(): void {
    // Papel criado depois da configuração: entra como lateral do autenticado (equivale ao insert do contrib).
    $mapa = $this->servico(self::CADEIA, [])->mapa();
    $this->assertSame(['authenticated'], $mapa['wiki_contributor'] ?? ['authenticated']);
  }

  public function testHerdadosNaoGravadosIgnoraOQueEstaNoBanco(): void {
    // Conta gravada com gerente_loja: lms_teacher e associado são herdados e não devem ser salvos.
    $servico = $this->servico(self::CADEIA, [], ['gerente_loja']);
    $naoGravados = $servico->herdadosNaoGravados(7);
    $this->assertContains('content_editor', $naoGravados);
    $this->assertNotContains('gerente_loja', $naoGravados);
    $this->assertSame([], $this->servico(self::CADEIA, [])->herdadosNaoGravados(0));
  }

}
