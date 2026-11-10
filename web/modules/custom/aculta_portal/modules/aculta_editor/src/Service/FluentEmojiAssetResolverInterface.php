<?php

declare(strict_types=1);

namespace Drupal\aculta_editor\Service;

/**
 * Contrato neutro para resolução de assets do Fluent Emoji.
 */
interface FluentEmojiAssetResolverInterface {

  /**
   * Retorna a URL base para os assets de emoji colorido.
   */
  public function getAssetsUrl(): string;

}
