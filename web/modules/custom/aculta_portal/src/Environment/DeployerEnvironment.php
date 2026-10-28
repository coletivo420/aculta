<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Environment;

/**
 * Resolves the site environment and domain hosts from ACULTA Deployer data.
 *
 * Invalid environment values use the supplied valid fallback.
 */
final class DeployerEnvironment {

  public const VALUES = ['production', 'test'];

  /**
   * Returns the HTTPS base URL declared for the current environment.
   *
   * @param string $projectRoot
   *   Project root above web/.
   *
   * @return string|null
   *   The site URL, or NULL when the mapping is missing or invalid.
   */
  public static function siteUrl(string $projectRoot): ?string {
    $file = rtrim($projectRoot, '/') . '/var/deployer/environment.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), TRUE) : NULL;
    $site = is_array($data) && ($data['schema'] ?? NULL) === 1 ? ($data['site'] ?? NULL) : NULL;
    if (is_string($site)
      && preg_match('~^https://[a-z0-9.-]+$~', $site) === 1) {
      return $site;
    }
    return NULL;
  }

  /**
   * Returns the environment declared by the Deployer.
   *
   * @param string $projectRoot
   *   Project root above web/.
   * @param string $fallback
   *   Valid environment to use when the Deployer file is unavailable.
   *
   * @return string
   *   The declared environment or the valid fallback.
   */
  public static function current(string $projectRoot, string $fallback = 'production'): string {
    $file = rtrim($projectRoot, '/') . '/var/deployer/environment.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), TRUE) : NULL;
    if (is_array($data)
      && ($data['schema'] ?? NULL) === 1
      && in_array($data['environment'] ?? NULL, self::VALUES, TRUE)) {
      return (string) $data['environment'];
    }
    return in_array($fallback, self::VALUES, TRUE) ? $fallback : 'production';
  }

  /**
   * Returns the domain host for the active Deployer environment.
   *
   * Uses `domain_transition.entries` as the single domain mapping source.
   *
   * Fails closed when the environment, domain, or host is ambiguous.
   */
  public static function domainHost(string $projectRoot, string $domainId): ?string {
    return self::domainHostForEnvironment($projectRoot, $domainId, self::current($projectRoot));
  }

  /**
   * Returns the host for a Deployer domain entry in an explicit environment.
   *
   * @param string $projectRoot
   *   Project root above web/.
   * @param string $domainId
   *   Domain ID to resolve.
   * @param string $environment
   *   Environment name, either test or production.
   *
   * @return string|null
   *   Valid hostname or NULL when the mapping is missing or ambiguous.
   */
  public static function domainHostForEnvironment(string $projectRoot, string $domainId, string $environment): ?string {
    if (!in_array($environment, self::VALUES, TRUE) || $domainId === '') {
      return NULL;
    }
    $file = rtrim($projectRoot, '/')
      . '/web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), TRUE) : NULL;
    $entries = is_array($data) ? ($data['domain_transition']['entries'] ?? NULL) : NULL;
    if (!is_array($entries)) {
      return NULL;
    }
    $hostKey = $environment === 'test' ? 'test_host' : 'production_host';
    $host = NULL;
    foreach ($entries as $entry) {
      if (!is_array($entry)
        || ($entry['domain_id'] ?? NULL) !== $domainId) {
        continue;
      }
      if ($host !== NULL) {
        return NULL;
      }
      $candidate = $entry[$hostKey] ?? NULL;
      if (!is_string($candidate)
        || preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i', $candidate) !== 1) {
        return NULL;
      }
      $host = strtolower($candidate);
    }
    return $host;
  }

}
