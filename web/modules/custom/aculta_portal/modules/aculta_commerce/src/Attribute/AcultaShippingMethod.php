<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the AcultaShippingMethod plugin attribute.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). The plugin
 * type and all identifiers are ACULTA-owned so they can coexist with the
 * contributed module while the port is being validated.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AcultaShippingMethod extends Plugin {

  /**
   * Constructs an AcultaShippingMethod attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The plugin label.
   * @param array $services
   *   Supported shipping services, as labels keyed by ID.
   * @param string $workflow
   *   The shipment workflow ID.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $services = [],
    public readonly string $workflow = 'aculta_shipment_default',
    public readonly ?string $deriver = NULL,
  ) {}

}
