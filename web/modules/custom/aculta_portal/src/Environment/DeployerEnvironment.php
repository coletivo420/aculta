<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Environment;

/**
 * Ambiente do site definido pelo ACULTA Deployer (arquivo neutro var/deployer/environment.json).
 *
 * Único ponto de leitura do ambiente para o Portal. Valores fora de production|test caem no padrão informado.
 */
final class DeployerEnvironment {

  public const VALUES = ['production', 'test'];

  /** @param string $projectRoot raiz do projeto (acima de web/) */
  /**
   * Endereço principal do ambiente declarado pelo aculta_deployer (ex.: https://aculta.toca.net.br), ou NULL.
   */
  public static function siteUrl(string $projectRoot): ?string {
    $file = rtrim($projectRoot, '/') . '/var/deployer/environment.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), TRUE) : NULL;
    $site = is_array($data) && ($data['schema'] ?? NULL) === 1 ? ($data['site'] ?? NULL) : NULL;
    if (is_string($site) && preg_match('~^https://[a-z0-9.-]+$~', $site) === 1) {
      return $site;
    }
    return NULL;
  }

  public static function current(string $projectRoot, string $fallback = 'production'): string {
    $file = rtrim($projectRoot, '/') . '/var/deployer/environment.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), TRUE) : NULL;
    if (is_array($data) && ($data['schema'] ?? NULL) === 1 && in_array($data['environment'] ?? NULL, self::VALUES, TRUE)) {
      return (string) $data['environment'];
    }
    return in_array($fallback, self::VALUES, TRUE) ? $fallback : 'production';
  }

}
