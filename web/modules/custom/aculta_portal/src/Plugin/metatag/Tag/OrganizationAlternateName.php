<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Registered trade name, never a replacement for public branding.
 *
 * @MetatagTag(
 *   id = "schema_organization_alternate_name", label = @Translation("alternateName"),
 *   description = @Translation("Nome fantasia registrado, quando confirmado."),
 *   name = "alternateName", group = "schema_organization", weight = 4,
 *   type = "string", secure = FALSE, multiple = FALSE, property_type = "text"
 * )
 */
class OrganizationAlternateName extends SchemaNameBase {}
