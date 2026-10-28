<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventDateOverride;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Série salva e lida do banco: regra, exceção por data original e saída em horário de Brasília.
 *
 * Datas de referência: 28/10/2026 é quarta-feira. O banco guarda UTC; a saída é em
 * horário de Brasília, no formato dd/mm/aaaa HH:mm.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventDateOccurrencesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'datetime', 'datetime_range', 'options', 'aculta_calendar', 'aculta_events'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_override');
  }

  /**
   * Evento de apoio: a data precisa pertencer a um evento.
   */
  private function evento(): \Drupal\aculta_events\Entity\Event {
    $evento = \Drupal\aculta_events\Entity\Event::create(['title' => 'Evento de teste']);
    $evento->save();
    return $evento;
  }

  /**
   * Converte horário de Brasília para o valor guardado (UTC, sem sufixo).
   */
  private function utc(string $brasilia): string {
    $momento = new \DateTimeImmutable($brasilia, new \DateTimeZone('America/Sao_Paulo'));
    return $momento->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
  }

  /**
   * Oficina semanal às segundas e quartas, 19h às 21h, três ocorrências.
   */
  private function serie(): EventDate {
    $data = EventDate::create([
      'field_evento' => $this->evento()->id(),
      'title' => 'Oficina de cerâmica',
      'field_quando' => [
        'value' => $this->utc('2026-10-28 19:00:00'),
        'end_value' => $this->utc('2026-10-28 21:00:00'),
      ],
      'field_regra' => 'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3',
      'field_limite_meses' => 12,
    ]);
    $data->save();
    return $data;
  }

  /**
   * Série lida do banco, com o cancelamento de 02/11 e o reagendamento de 04/11.
   */
  public function testSerieComExcecoesEmHorarioDeBrasilia(): void {
    $data = $this->serie();

    EventDateOverride::create([
      'field_data_pai' => $data->id(),
      'field_inicio_original' => $this->utc('2026-11-02 19:00:00'),
      'field_inicio' => NULL,
      'field_fim' => NULL,
      'field_motivo' => 'Feriado local',
    ])->save();

    EventDateOverride::create([
      'field_data_pai' => $data->id(),
      'field_inicio_original' => $this->utc('2026-11-04 19:00:00'),
      'field_inicio' => $this->utc('2026-11-05 20:00:00'),
      'field_fim' => $this->utc('2026-11-05 22:00:00'),
    ])->save();

    $servico = $this->container->get('aculta_events.occurrences');
    $ocorrencias = $servico->expandir(
      EventDate::load($data->id()),
      new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('America/Sao_Paulo')),
      new \DateTimeImmutable('2027-06-30 23:59:59', new \DateTimeZone('America/Sao_Paulo')),
      new \DateTimeImmutable('2026-10-28 00:00:00', new \DateTimeZone('America/Sao_Paulo')),
    );

    $this->assertSame(
      ['28/10/2026 19:00', '02/11/2026 19:00', '05/11/2026 20:00'],
      array_map(static fn (array $o): string => $o['inicio']->format('d/m/Y H:i'), $ocorrencias),
    );
    $this->assertSame([FALSE, TRUE, FALSE], array_column($ocorrencias, 'cancelada'));
  }

  /**
   * Data sem regra é uma ocorrência só, se cair na janela.
   */
  public function testDataUnicaSoApareceNaJanela(): void {
    $data = EventDate::create([
      'field_evento' => $this->evento()->id(),
      'title' => 'Palestra',
      'field_quando' => [
        'value' => $this->utc('2026-11-10 18:30:00'),
        'end_value' => $this->utc('2026-11-10 20:00:00'),
      ],
      'field_regra' => '',
    ]);
    $data->save();

    $servico = $this->container->get('aculta_events.occurrences');
    $dentro = $servico->expandir(
      $data,
      new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('America/Sao_Paulo')),
      new \DateTimeImmutable('2026-11-30 23:59:59', new \DateTimeZone('America/Sao_Paulo')),
      new \DateTimeImmutable('2026-10-28 00:00:00', new \DateTimeZone('America/Sao_Paulo')),
    );
    $fora = $servico->expandir(
      $data,
      new \DateTimeImmutable('2026-12-01 00:00:00', new \DateTimeZone('America/Sao_Paulo')),
      new \DateTimeImmutable('2026-12-31 23:59:59', new \DateTimeZone('America/Sao_Paulo')),
      new \DateTimeImmutable('2026-10-28 00:00:00', new \DateTimeZone('America/Sao_Paulo')),
    );

    $this->assertCount(1, $dentro);
    $this->assertSame('10/11/2026 18:30', $dentro[0]['inicio']->format('d/m/Y H:i'));
    $this->assertSame([], $fora);
  }

}
