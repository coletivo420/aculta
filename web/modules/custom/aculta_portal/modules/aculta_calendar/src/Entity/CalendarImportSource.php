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
    'lastSync',
    'lastResult',
  ],
)]
final class CalendarImportSource extends ConfigEntityBase {

  /** URL do arquivo, sem acesso de rede nesta microfase. */
  protected string $sourceUrl = '';

  /** Timestamp UTC do último processamento, zero quando nunca processada. */
  protected int $lastSync = 0;

  /** Resultado curto do último processamento. */
  protected string $lastResult = 'never';

  public function sourceUrl(): string {
    return $this->sourceUrl;
  }

  public function lastSync(): int {
    return $this->lastSync;
  }

  public function lastResult(): string {
    return $this->lastResult;
  }

}
