<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_agenda\Unit;

use Drupal\aculta_lms_agenda\Calendar\PersonalCalendarSeries;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Agenda entries become stable ICS series with cancellation labels.
 */
#[Group('aculta_lms_agenda')]
class PersonalCalendarSeriesTest extends UnitTestCase {

  /**
   * Prazo sem fim; evento com fim; cancelado com prefixo; chave repetível.
   */
  public function testMontaSeriesEstaveis(): void {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = new \DateTimeImmutable('2026-10-28 10:00', $fuso);
    $entradas = [
      [
        'tipo' => 'prazo',
        'titulo' => 'Prazo: Leitura',
        'inicio' => new \DateTimeImmutable('2026-11-02 12:00', $fuso),
        'fim' => new \DateTimeImmutable('2026-11-02 12:00', $fuso),
        'cancelada' => FALSE,
        'curso' => 1,
        'turma' => 5,
      ],
      [
        'tipo' => 'evento',
        'titulo' => 'Roda',
        'inicio' => new \DateTimeImmutable('2026-11-04 19:00', $fuso),
        'fim' => new \DateTimeImmutable('2026-11-04 21:00', $fuso),
        'cancelada' => TRUE,
        'curso' => 1,
        'turma' => 5,
      ],
    ];
    $series = PersonalCalendarSeries::paraIcs($entradas, $agora);

    $this->assertNull($series[0]['end'], 'prazo não tem fim');
    $this->assertSame('[Cancelado] Roda', $series[1]['summary']);
    $this->assertSame(PersonalCalendarSeries::paraIcs($entradas, $agora)[0]['key'], $series[0]['key'], 'chave estável');
  }

}
