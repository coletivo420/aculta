<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Controller;

use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthClient;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapEnvironment;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapTokenStore;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Read-only status page for the OpenStreetMap integration.
 */
final class OpenStreetMapStatusController extends ControllerBase {

  /**
   * Cria a página sem acesso ao valor de tokens ou segredos.
   */
  public function __construct(
    private readonly OpenStreetMapOAuthClient $client,
    private readonly OpenStreetMapEnvironment $environment,
    private readonly OpenStreetMapTokenStore $tokens,
  ) {}

  /**
   * Exibe estado do ambiente e da conexão sem revelar credenciais. */
  public function page(): array {
    $environment = $this->environment->environment();
    $connected = $this->tokens->isConnected();
    $configured = $this->client->isConfigured();
    $build = [
      'environment' => [
        '#type' => 'item',
        '#title' => $this->t('Ambiente OSM'),
        '#markup' => $environment === NULL
          ? $this->t('Bloqueado: o ambiente OSM precisa corresponder ao ambiente do site.')
          : ($environment === 'test' ? $this->t('Desenvolvimento do OpenStreetMap') : $this->t('Produção do OpenStreetMap')),
      ],
      'connection' => [
        '#type' => 'item',
        '#title' => $this->t('Conexão'),
        '#markup' => $connected ? $this->t('Autorizada') : $this->t('Não autorizada'),
      ],
      'credentials' => [
        '#type' => 'item',
        '#title' => $this->t('Credenciais da aplicação'),
        '#markup' => $configured ? $this->t('Configuradas') : $this->t('Ausentes ou incompletas'),
      ],
      '#cache' => ['max-age' => 0],
    ];
    if ($configured && $environment !== NULL) {
      $build['authorize'] = [
        '#type' => 'link',
        '#title' => $connected ? $this->t('Reautorizar OpenStreetMap') : $this->t('Conectar OpenStreetMap'),
        '#url' => Url::fromRoute('aculta_events_openstreetmap.oauth_authorize'),
        '#attributes' => ['data-aculta-popup' => 'openstreetmap'],
        '#attached' => ['library' => ['aculta_portal/integration-popup']],
      ];
    }
    return $build;
  }

}
