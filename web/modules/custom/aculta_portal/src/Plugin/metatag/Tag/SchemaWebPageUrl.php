<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * @MetatagTag(
 *   id = "schema_web_page_url",
 *   label = @Translation("url"),
 *   description = @Translation("Canonical URL of the web page."),
 *   name = "url",
 *   group = "schema_web_page",
 *   weight = -7,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "url",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageUrl extends SchemaNameBase {
}
