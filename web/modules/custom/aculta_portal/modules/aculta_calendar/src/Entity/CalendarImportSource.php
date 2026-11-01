<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Fonte configurada de arquivo de calendário externo.
 */
#[ConfigEntityType(
  id: 'aculta_calendar_import_source',
  label: new TranslatableMarkup('Fonte de calendário importado'),
  label_collection: new TranslatableMarkup('Fontes de calendário importado'),
  label_singular: new TranslatableMarkup('fonte de calendário'),
  label_plural: new TranslatableMarkup('fontes de calendário'),
  config_prefix: 'import_source',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'status' => 'status',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
    'status',
    'sourceUrl',
    'allowedHosts',
    'lastSync',
    'lastResult',
  ],
)]
final class CalendarImportSource extends ConfigEntityBase {

  /**
   * HTTPS URL of the source iCalendar file.
   *
   * @var string
   */
  protected string $sourceUrl = '';

  /**
   * Exact HTTPS hostnames allowed for this source, without wildcards.
   *
   * @var string[]
   */
  protected array $allowedHosts = [];

  /**
   * UTC timestamp of the last processing attempt, or zero if never processed.
   *
   * @var int
   */
  protected int $lastSync = 0;

  /**
   * Short result code for the last processing attempt.
   *
   * @var string
   */
  protected string $lastResult = 'never';

  /**
   * Returns the HTTPS URL configured for this source.
   *
   * @return string
   *   The configured source URL.
   */
  public function sourceUrl(): string {
    return $this->sourceUrl;
  }

  /**
   * Returns the exact hostnames allowed for this source.
   *
   * @return string[]
   *   The configured hostnames.
   */
  public function allowedHosts(): array {
    return $this->allowedHosts;
  }

  /**
   * Returns the UTC timestamp of the last processing attempt.
   *
   * @return int
   *   The timestamp, or zero if no attempt has occurred.
   */
  public function lastSync(): int {
    return $this->lastSync;
  }

  /**
   * Returns the short result code from the last processing attempt.
   *
   * @return string
   *   The result code.
   */
  public function lastResult(): string {
    return $this->lastResult;
  }

}
