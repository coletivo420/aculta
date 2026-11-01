<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Leitura das configurações do Melhor Envio. Ambiente inválido cai no sandbox, nunca na produção.
 *
 * Só guarda nomes de chaves do Drupal Key, nunca credenciais.
 */
final class MelhorEnvioConfig {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Ambiente ativo. Valor inválido ou ausente cai no sandbox.
   *
   * @return MelhorEnvioEnvironment
   *   Ambiente ativo.
   */
  public function environment(): MelhorEnvioEnvironment {
    $value = (string) $this->settings()->get('environment');
    return MelhorEnvioEnvironment::tryFrom($value) ?? MelhorEnvioEnvironment::Sandbox;
  }

  /**
   * URL de retorno configurada. Lança erro quando ausente.
   *
   * @return string
   *   Texto.
   */
  public function redirectUri(): string {
    return (string) $this->settings()->get('redirect_uri');
  }

  /**
   * Escopos OAuth2 separados por espaço. Vazio até a confirmação dos escopos oficiais.
   *
   * @return string
   *   Texto.
   */
  public function scopes(): string {
    return (string) $this->settings()->get('scopes');
  }

  /**
   * E-mail de contato exigido no User-Agent da API. Vazio quando não configurado.
   *
   * @return string
   *   Texto.
   */
  public function contactEmail(): string {
    return (string) $this->settings()->get('contact_email');
  }

  /**
   * Nomes das chaves do Drupal Key para o ambiente informado.
   *
   * @return array{client_id:
   *   string, client_secret: string}
   */
  public function keyIds(MelhorEnvioEnvironment $environment): array {
    $keys = $this->settings()->get('keys') ?? [];
    $pair = is_array($keys[$environment->value] ?? NULL) ? $keys[$environment->value] : [];
    return [
      'client_id' => (string) ($pair['client_id'] ?? ''),
      'client_secret' => (string) ($pair['client_secret'] ?? ''),
    ];
  }

  /**
   * Configuração imutável do módulo.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Configuração do módulo.
   */
  private function settings(): ImmutableConfig {
    return $this->configFactory->get('aculta_commerce_melhorenvio.settings');
  }

}
