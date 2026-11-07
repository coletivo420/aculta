<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\metatag\Tag;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Preserve commas inside a single street address in structured properties. */
class PostalAddressTag extends SchemaNameBase {

  /**
   * Preserva as vírgulas de um endereço de rua em propriedade estruturada.
   *
   * @param mixed $value
   *   Valor do item, alterado no lugar.
   * @param int|string $key
   *   Chave do item processado.
   */
  protected function processItem(&$value, $key = 0) {
    if ($key === 'streetAddress' || $key === 'name') {
      $value = trim(PlainTextOutput::renderFromHtml($value));
      return;
    }
    parent::processItem($value, $key);
  }

}
