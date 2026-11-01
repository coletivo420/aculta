<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_calendar\Agenda\CalendarOccurrences;
use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Exports a bounded period of event occurrences through the calendar provider.
 */
final class AdministrativeEventExportController extends ControllerBase {

  private const MAX_DAYS = 366;

  /**
   * Constructs the export controller.
   */
  public function __construct(
    private readonly CalendarItemSource $source,
    private readonly CalendarOccurrences $occurrences,
    private readonly TimeInterface $time,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_calendar.item_source'),
      $container->get('aculta_calendar.occurrences'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
    );
  }

  /**
   * Returns a CSV for published event dates in the requested period.
   */
  public function export(): Response {
    $timezone = new \DateTimeZone('America/Sao_Paulo');
    $now = (new \DateTimeImmutable('@' . $this->time->getCurrentTime()))->setTimezone($timezone);
    $request = $this->requestStack->getCurrentRequest();
    $from = $this->parseDate($request?->query->get('de'), $now->setTime(0, 0));
    $to = $this->parseDate($request?->query->get('ate'), $from->modify('+30 days'));
    if ($to < $from || $from->diff($to)->days > self::MAX_DAYS) {
      throw new BadRequestHttpException('O período deve ter até 366 dias e a data final não pode anteceder a inicial.');
    }
    $query = new CalendarQuery($from, $to->setTime(23, 59, 59), $now);
    $rows = [];
    foreach ($this->source->items($query) as $item) {
      if (!$item instanceof CalendarItem || $item->origem !== 'aculta_events') {
        continue;
      }
      foreach ($this->occurrences->expandir($item, $query) as $occurrence) {
        $rows[] = [
          'timestamp' => $occurrence['inicio']->getTimestamp(),
          'values' => [
            $item->titulo,
            $occurrence['inicio']->format('d/m/Y H:i'),
            $occurrence['fim']->format('d/m/Y H:i'),
            $item->diaInteiro ? 'sim' : 'não',
            $occurrence['cancelada'] ? 'cancelado' : 'publicado',
            $item->local,
            $item->link ?? '',
          ],
        ];
      }
    }
    usort($rows, static fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

    $stream = fopen('php://temp', 'w+b');
    if ($stream === FALSE) {
      throw new \RuntimeException('Não foi possível criar o arquivo CSV temporário.');
    }
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, ['Evento', 'Início', 'Fim', 'Dia inteiro', 'Situação', 'Local', 'Link'], ';', '"', '\\', "\r\n");
    foreach ($rows as $row) {
      fputcsv($stream, array_map([$this, 'safeSpreadsheetCell'], $row['values']), ';', '"', '\\', "\r\n");
    }
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    return new Response((string) $content, 200, [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="eventos-aculta.csv"',
      'Cache-Control' => 'private, no-store',
    ]);
  }

  /**
   * Parses an optional Brazilian date and rejects malformed values.
   */
  private function parseDate(mixed $value, \DateTimeImmutable $default): \DateTimeImmutable {
    if ($value === NULL || $value === '') {
      return $default;
    }
    if (!is_string($value)) {
      throw new BadRequestHttpException('Use datas no formato dd/mm/aaaa.');
    }
    $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $value, $default->getTimezone());
    $errors = \DateTimeImmutable::getLastErrors();
    if ($date === FALSE || ($errors !== FALSE && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('d/m/Y') !== $value) {
      throw new BadRequestHttpException('Use datas válidas no formato dd/mm/aaaa.');
    }
    return $date;
  }

  /**
   * Prefixes spreadsheet formulas in textual CSV fields with an apostrophe.
   */
  private function safeSpreadsheetCell(string $value): string {
    return preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $value) ? "'" . $value : $value;
  }

}
