<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Captcha;

use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorableConfigBase;

/**
 * Escolhe a chave do Turnstile pelo ambiente do deployer, sem gravar nada na configuração.
 *
 * Dois ambientes: production → chave de produção (Key "turnstile", TURNSTILE_KEYS_JSON, no banco criptografado);
 * test → chave de teste (Key "turnstile_test", TURNSTILE_TEST_KEYS_JSON). O ambiente local é do tipo teste.
 */
final class TurnstileKeyOverride implements ConfigFactoryOverrideInterface {

  private const CONFIG_NAME = 'turnstile.settings';

  public function __construct(private readonly string $appRoot) {}

  public function loadOverrides($names): array {
    if (!in_array(self::CONFIG_NAME, $names, TRUE)) {
      return [];
    }
    $environment = DeployerEnvironment::current(dirname($this->appRoot), 'production');
    $key = $environment === 'test' ? 'turnstile_test' : 'turnstile';
    return [self::CONFIG_NAME => ['keys' => $key]];
  }

  public function getCacheSuffix(): string {
    return 'aculta_portal_turnstile_key_' . DeployerEnvironment::current(dirname($this->appRoot), 'production');
  }

  public function createConfigObject($name, $collection = StorableConfigBase::DEFAULT_COLLECTION) {
    return NULL;
  }

  public function getCacheableMetadata($name): \Drupal\Core\Cache\CacheableMetadata {
    return new \Drupal\Core\Cache\CacheableMetadata();
  }

}
