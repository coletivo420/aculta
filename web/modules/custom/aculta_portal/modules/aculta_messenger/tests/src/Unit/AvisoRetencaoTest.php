<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoRetencao;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Retenção de seis meses dos avisos do Fórum (ACULTA Messenger). Persistentes e avisos do sistema não entram aqui.
 */
#[Group('aculta_messenger')]
class AvisoRetencaoTest extends UnitTestCase {

  /** Aviso criado há mais de seis meses está vencido; aviso recente não. */
  public function testVencidoDepoisDeSeisMeses(): void {
    $agora = (int) (new \DateTimeImmutable('2026-10-16 12:00:00 UTC'))->getTimestamp();
    $criadoHaSeisMeses = (int) (new \DateTimeImmutable('2026-04-16 12:00:00 UTC'))->getTimestamp();
    $criadoHaCincoMeses = (int) (new \DateTimeImmutable('2026-05-16 12:00:00 UTC'))->getTimestamp();

    $this->assertTrue(AvisoRetencao::vencido($criadoHaSeisMeses - 1, $agora));
    $this->assertTrue(AvisoRetencao::vencido($criadoHaSeisMeses, $agora));
    $this->assertFalse(AvisoRetencao::vencido($criadoHaCincoMeses, $agora));
  }

  /** Só os tipos do Fórum são sujeitos à retenção. */
  public function testTiposSujeitosSaoOsDoForum(): void {
    $this->assertSame([Aviso::TIPO_RESPOSTA, Aviso::TIPO_ATUALIZACAO], AvisoRetencao::TIPOS);
  }

}
