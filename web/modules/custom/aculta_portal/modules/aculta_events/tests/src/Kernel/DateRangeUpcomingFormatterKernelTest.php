<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\EventDate;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Formatter de datas futuras: formato brasileiro, datas passadas ocultas e cache até o fim da data (T5).
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class DateRangeUpcomingFormatterKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'datetime', 'datetime_range', 'aculta_calendar', 'aculta_events'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_event_date');
  }

  /**
   * Converte horário de Brasília para o valor guardado (UTC, sem sufixo).
   */
  private function utc(string $brasilia): string {
    return (new \DateTimeImmutable($brasilia, new \DateTimeZone('America/Sao_Paulo')))
      ->setTimezone(new \DateTimeZone('UTC'))
      ->format('Y-m-d\TH:i:s');
  }

  /**
   * Data no mesmo dia: "dd/mm/aaaa HH:mm – HH:mm", em 24 horas.
   */
  public function testMostraDataFuturaNoFormatoBrasileiro(): void {
    $data = EventDate::create([
      'title' => 'Feira',
      'field_quando' => [
        'value' => $this->utc('2030-05-10 19:00:00'),
        'end_value' => $this->utc('2030-05-10 21:00:00'),
      ],
    ]);
    $data->save();

    $elementos = $data->get('field_quando')->view(['type' => 'aculta_daterange_upcoming']);
    $this->assertSame('10/05/2030 19:00 – 21:00', $elementos[0]['#markup']);
  }

  /**
   * Data que já terminou não aparece.
   */
  public function testOcultaDataPassada(): void {
    $data = EventDate::create([
      'title' => 'Antiga',
      'field_quando' => [
        'value' => $this->utc('2020-01-10 19:00:00'),
        'end_value' => $this->utc('2020-01-10 21:00:00'),
      ],
    ]);
    $data->save();

    $elementos = $data->get('field_quando')->view(['type' => 'aculta_daterange_upcoming']);
    $this->assertArrayNotHasKey(0, $elementos);
  }

  /**
   * O cache do elemento expira no fim da data, não num cron diário (T5).
   */
  public function testCacheExpiraNoFimDaData(): void {
    $data = EventDate::create([
      'title' => 'Feira',
      'field_quando' => [
        'value' => $this->utc('2030-05-10 19:00:00'),
        'end_value' => $this->utc('2030-05-10 21:00:00'),
      ],
    ]);
    $data->save();

    $elementos = $data->get('field_quando')->view(['type' => 'aculta_daterange_upcoming']);
    $fim = (new \DateTimeImmutable('2030-05-10 21:00:00', new \DateTimeZone('America/Sao_Paulo')))->getTimestamp();
    $agora = $this->container->get('datetime.time')->getCurrentTime();
    $this->assertEqualsWithDelta($fim - $agora, $elementos['#cache']['max-age'], 5);
  }

}
