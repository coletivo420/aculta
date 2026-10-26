<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\physical\Weight;

/**
 * Immutable package type used as fallback for shipments without dimensions.
 *
 * The contributed module ships a full plugin system for package types. The
 * ACULTA engine starts with a small built-in set; carrier integrations may
 * request a specific package type by ID.
 */
final class PackageType {

  /**
   * Constructs a new PackageType object.
   */
  public function __construct(
    protected string $id,
    protected string $label,
    protected Weight $weight,
    protected ?array $dimensions = NULL,
  ) {}

  /**
   * Gets the package type ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the package type label.
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * Gets the package weight.
   */
  public function getWeight(): Weight {
    return $this->weight;
  }

  /**
   * Gets the package dimensions.
   *
   * @return array{length: \Drupal\physical\Length, width: \Drupal\physical\Length, height: \Drupal\physical\Length}|null
   *   The dimensions, or NULL when unknown.
   */
  public function getDimensions(): ?array {
    return $this->dimensions;
  }

}
