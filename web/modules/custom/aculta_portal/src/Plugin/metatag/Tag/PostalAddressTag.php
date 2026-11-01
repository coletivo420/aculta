<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\metatag\Tag;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/** Preserve commas inside a single street address in structured properties. */
class PostalAddressTag extends SchemaNameBase {
  protected function processItem(&$value, $key = 0) {
    if ($key === 'streetAddress' || $key === 'name') {
      $value = trim(PlainTextOutput::renderFromHtml($value));
      return;
    }
    parent::processItem($value, $key);
  }
}
