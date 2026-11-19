<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\physical\Length;
use Drupal\physical\LengthUnit;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;

/**
 * Provides the built-in package types of the ACULTA shipping engine.
 *
 * This replaces the contributed package type plugin manager with a small,
 * explicit set. Unknown IDs fall back to the default box, but the fallback is
 * never used to invent a shipping rate.
 */
final class PackageTypeManager {

  /**
   * Cached canonical definitions shared by the service and entity value API.
   *
   * @var array<string, \Drupal\aculta_commerce\Shipping\PackageType>|null
   */
  private static ?array $builtInPackageTypes = NULL;

  /**
   * The built-in package types.
   *
   * @var array<string, \Drupal\aculta_commerce\Shipping\PackageType>
   */
  protected array $packageTypes = [];

  /**
   * Constructs a new PackageTypeManager object.
   */
  public function __construct() {
    $this->packageTypes = self::buildPackageTypes();
  }

  /**
   * Gets a package type by ID.
   */
  public function getPackageType(string $package_type_id): PackageType {
    return self::getBuiltInPackageType($package_type_id);
  }

  /**
   * Gets a built-in package type without requiring the service container.
   */
  public static function getBuiltInPackageType(string $package_type_id): PackageType {
    $package_types = self::buildPackageTypes();
    return $package_types[$package_type_id] ?? $package_types['custom_box'];
  }

  /**
   * Gets all package type definitions.
   *
   * @return array<string, \Drupal\aculta_commerce\Shipping\PackageType>
   *   The package types, keyed by ID.
   */
  public function getPackageTypes(): array {
    return $this->packageTypes;
  }

  /**
   * Creates the canonical built-in package type definitions.
   *
   * @return array<string, \Drupal\aculta_commerce\Shipping\PackageType>
   *   The package types, keyed by ID.
   */
  private static function buildPackageTypes(): array {
    return self::$builtInPackageTypes ??= [
      'custom_box' => new PackageType(
        'custom_box',
        'Custom box',
        new Weight('0', WeightUnit::GRAM),
        [
          'length' => new Length('0', LengthUnit::CENTIMETER),
          'width' => new Length('0', LengthUnit::CENTIMETER),
          'height' => new Length('0', LengthUnit::CENTIMETER),
        ],
      ),
      'letter' => new PackageType(
        'letter',
        'Letter',
        new Weight('20', WeightUnit::GRAM),
        [
          'length' => new Length('22', LengthUnit::CENTIMETER),
          'width' => new Length('11', LengthUnit::CENTIMETER),
          'height' => new Length('1', LengthUnit::CENTIMETER),
        ],
      ),
    ];
  }

}
