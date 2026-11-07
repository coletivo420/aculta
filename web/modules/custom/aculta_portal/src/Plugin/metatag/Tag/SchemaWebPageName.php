<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Define a metatag do nome da página web do schema.
 *
 * @MetatagTag(
 *   id = "schema_web_page_name",
 *   label = @Translation("name"),
 *   description = @Translation("Name of the web page."),
 *   name = "name",
 *   group = "schema_web_page",
 *   weight = -8,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageName extends SchemaNameBase {
}
