<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

use Drupal\aculta_calendar\Entity\CalendarImportSource;

/**
 * Coordinates secure remote fetch, local parsing, normalization, and import.
 */
final class CalendarRemoteImportService {

  public function __construct(
    private readonly CalendarIcsRemoteFetcher $fetcher,
    private readonly CalendarFileReaderInterface $reader,
    private readonly CalendarEventNormalizer $normalizer,
    private readonly CalendarImportProcessor $processor,
  ) {}

  /**
   * Imports one active configured source into editorial drafts.
   *
   * @return array{created: int, updated: int, unchanged: int, absent: int}
   *   Reconciliation counters from the import processor.
   */
  public function import(CalendarImportSource $source): array {
    if (!$source->status()) {
      throw new \InvalidArgumentException('A fonte iCalendar está desativada.');
    }
    if ($source->allowedHosts() === []) {
      throw new \InvalidArgumentException('Configure ao menos um host na allowlist da fonte iCalendar.');
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), 'aculta-calendar-');
    if ($temporaryPath === FALSE) {
      throw new \RuntimeException('Não foi possível criar arquivo temporário para importar o calendário.');
    }
    try {
      $contents = $this->fetcher->fetch($source->sourceUrl(), $source->allowedHosts());
      if (file_put_contents($temporaryPath, $contents, LOCK_EX) !== strlen($contents)) {
        throw new \RuntimeException('Não foi possível guardar temporariamente o arquivo iCalendar remoto.');
      }
      $events = array_map($this->normalizer->normalize(...), $this->reader->read($temporaryPath));
      return $this->processor->process($source, $events);
    }
    catch (\Throwable $exception) {
      $source->set('lastSync', time());
      $source->set('lastResult', 'error');
      $source->save();
      throw $exception;
    }
    finally {
      @unlink($temporaryPath);
    }
  }

}
