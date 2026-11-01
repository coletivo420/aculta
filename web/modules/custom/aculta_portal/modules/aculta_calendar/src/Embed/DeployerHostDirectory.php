<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Embed;

use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Read-only domain hosts declared by ACULTA Deployer.
 */
final class DeployerHostDirectory {

  public function __construct(
    private readonly ModuleExtensionList $modules,
  ) {}

  /**
   * Returns validated domain mappings for both environments.
   *
   * @return array<array{domain_id: string, test_host: string, production_host: string}>
   *   Valid, unambiguous entries sorted by domain ID.
   */
  public function mappings(): array {
    $path = $this->modules->getPath('aculta_deployer') . '/config/deploy.json';
    if (!is_file($path)) {
      return [];
    }
    try {
      $data = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return [];
    }
    return self::mappingsFromData(is_array($data) ? $data : []);
  }

  /**
   * Returns all mapped test and production hosts without duplicates.
   *
   * @return string[]
   *   Hostnames sorted alphabetically.
   */
  public function hosts(): array {
    $hosts = [];
    foreach ($this->mappings() as $mapping) {
      $hosts[] = $mapping['test_host'];
      $hosts[] = $mapping['production_host'];
    }
    $hosts = array_values(array_unique($hosts));
    sort($hosts);
    return $hosts;
  }

  /**
   * Extracts validated host mappings from Deployer JSON.
   *
   * @param array<mixed> $data
   *   Decoded deploy.json.
   *
   * @return array<array{domain_id: string, test_host: string, production_host: string}>
   *   Valid, unambiguous entries sorted by domain ID.
   */
  public static function mappingsFromData(array $data): array {
    $entries = $data['domain_transition']['entries'] ?? NULL;
    if (!is_array($entries)) {
      return [];
    }

    $counts = [];
    foreach ($entries as $entry) {
      if (is_array($entry) && is_string($entry['domain_id'] ?? NULL)) {
        $counts[$entry['domain_id']] = ($counts[$entry['domain_id']] ?? 0) + 1;
      }
    }

    $mappings = [];
    foreach ($entries as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $domainId = $entry['domain_id'] ?? NULL;
      $testHost = $entry['test_host'] ?? NULL;
      $productionHost = $entry['production_host'] ?? NULL;
      if (!is_string($domainId)
        || preg_match('/^[a-z0-9_]+$/', $domainId) !== 1
        || ($counts[$domainId] ?? 0) !== 1
        || !self::isHost($testHost)
        || !self::isHost($productionHost)) {
        continue;
      }
      $mappings[] = [
        'domain_id' => $domainId,
        'test_host' => strtolower($testHost),
        'production_host' => strtolower($productionHost),
      ];
    }
    usort($mappings, static fn (array $a, array $b): int => $a['domain_id'] <=> $b['domain_id']);
    return $mappings;
  }

  /**
   * Checks a hostname from Deployer without accepting URLs or wildcards.
   */
  private static function isHost(mixed $host): bool {
    return is_string($host)
      && preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i', $host) === 1;
  }

}
