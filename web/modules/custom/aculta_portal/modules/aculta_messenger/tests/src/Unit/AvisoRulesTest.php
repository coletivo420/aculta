<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regras puras dos avisos por pessoa (ACULTA Messenger, F5.2): deduplicação e intervalo do resumo.
 */
#[Group('aculta_messenger')]
class AvisoRulesTest extends UnitTestCase {

  /** Comentário novo conta; repetição do mesmo comentário não conta. */
  public function testComentarioSoContaUmaVez(): void {
    $this->assertTrue(AvisoService::isNewComment(10, 0));
    $this->assertTrue(AvisoService::isNewComment(11, 10));
    $this->assertFalse(AvisoService::isNewComment(10, 10));
    $this->assertFalse(AvisoService::isNewComment(9, 10));
  }

  /** Revisão nova conta; a mesma revisão não conta de novo. */
  public function testRevisaoSoContaUmaVez(): void {
    $this->assertTrue(AvisoService::isNewRevision(5, 0));
    $this->assertFalse(AvisoService::isNewRevision(5, 5));
    $this->assertFalse(AvisoService::isNewRevision(4, 5));
  }

  /** O resumo não envia antes de 20 horas desde o último envio. */
  public function testResumoRespeitaIntervalo(): void {
    $agora = 1_000_000;
    $this->assertTrue(AvisosResumo::podeEnviar($agora, 0));
    $this->assertFalse(AvisosResumo::podeEnviar($agora, $agora - 3600));
    $this->assertTrue(AvisosResumo::podeEnviar($agora, $agora - AvisosResumo::INTERVALO_SEGUNDOS));
  }

}
