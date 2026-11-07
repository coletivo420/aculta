<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Captcha;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Escolhe a chave do Turnstile pelo ambiente do deployer, sem gravar nada na configuração.
 *
 * Dois ambientes: production → chave de produção (Key "turnstile", TURNSTILE_KEYS_JSON, no banco criptografado);
 * test → chave de teste (Key "turnstile_test", TURNSTILE_TEST_KEYS_JSON). O ambiente local é do tipo teste.
 */
final class TurnstileKeyOverride implements ConfigFactoryOverrideInterface {

  private const CONFIG_NAME = 'turnstile.settings';

  public function __construct(private readonly string $appRoot) {}

  /**
   * Aplica a chave do Turnstile conforme o ambiente do deployer.
   *
   * @param array $names
   *   Nomes das configurações carregadas.
   *
   * @return array
   *   Sobrescritas da configuração do Turnstile.
   */
  public function loadOverrides($names): array {
    if (!in_array(self::CONFIG_NAME, $names, TRUE)) {
      return [];
    }
    $environment = DeployerEnvironment::current(dirname($this->appRoot), 'production');
    $key = $environment === 'test' ? 'turnstile_test' : 'turnstile';
    return [self::CONFIG_NAME => ['keys' => $key]];
  }

  /**
   * Devolve o sufixo de cache da sobrescrita.
   *
   * @return string
   *   Sufixo composto pelo ambiente atual.
   */
  public function getCacheSuffix(): string {
    return 'aculta_portal_turnstile_key_' . DeployerEnvironment::current(dirname($this->appRoot), 'production');
  }

  /**
   * Não cria objetos de configuração, pois só sobrescreve valores.
   *
   * @param string $name
   *   Nome da configuração.
   * @param string $collection
   *   Coleção de configuração.
   *
   * @return mixed|null
   *   Sempre NULL.
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

  /**
   * Devolve os metadados de cache da sobrescrita.
   *
   * @param string $name
   *   Nome da configuração.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Metadados de cache vazios.
   */
  public function getCacheableMetadata($name): CacheableMetadata {
    return new CacheableMetadata();
  }

}
