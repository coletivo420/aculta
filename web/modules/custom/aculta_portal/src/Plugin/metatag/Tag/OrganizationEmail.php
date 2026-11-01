<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Official public email address.
 *
 * @MetatagTag(
 *   id = "schema_organization_email", label = @Translation("email"),
 *   description = @Translation("E-mail institucional público confirmado."),
 *   name = "email", group = "schema_organization", weight = 6,
 *   type = "string", secure = FALSE, multiple = FALSE, property_type = "text"
 * )
 */
class OrganizationEmail extends SchemaNameBase {}
