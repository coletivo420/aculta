<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Official legal name, absent from the upstream Organization group.
 *
 * @MetatagTag(
 *   id = "schema_organization_legal_name", label = @Translation("legalName"),
 *   description = @Translation("Nome empresarial oficial da organização."),
 *   name = "legalName", group = "schema_organization", weight = 3,
 *   type = "string", secure = FALSE, multiple = FALSE, property_type = "text"
 * )
 */
class OrganizationLegalName extends SchemaNameBase {}
