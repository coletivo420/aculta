<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Verified CNPJ.
 *
 * @MetatagTag(
 *   id = "schema_organization_tax_id", label = @Translation("taxID"),
 *   description = @Translation("CNPJ oficial da organização."),
 *   name = "taxID", group = "schema_organization", weight = 5,
 *   type = "string", secure = FALSE, multiple = FALSE, property_type = "text"
 * )
 */
class OrganizationTaxId extends SchemaNameBase {}
