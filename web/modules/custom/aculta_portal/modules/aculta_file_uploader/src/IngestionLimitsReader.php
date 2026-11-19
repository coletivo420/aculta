<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Lê os limites de ingestão salvos no painel de uploads.
 *
 * Configuração ausente ou apagada por um import volta aos padrões de `IngestionLimits`.
 */
final class IngestionLimitsReader {

  /**
   * Nome do objeto de configuração.
   */
  public const CONFIG_NAME = 'aculta_file_uploader.ingestion_limits';

  /**
   * Constructs the reader.
   */
  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * Limites atuais.
   */
  public function get(): IngestionLimits {
    $stored = $this->configFactory->get(self::CONFIG_NAME)->get();
    $limites = [];
    if (is_array($stored)) {
      foreach ($stored as $chave => $valor) {
        if (is_string($chave)) {
          $limites[$chave] = $valor;
        }
      }
    }
    return IngestionLimits::fromArray($limites);
  }

}
