<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Controller;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioConfig;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioTokenStoreInterface;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Página de administração da conexão com o Melhor Envio: ambiente, estado da autorização e botão de conexão.
 *
 * O botão abre a autorização num popup (biblioteca aculta_portal/integration-popup). O retorno fecha o popup e
 * recarrega esta página, onde o tema exibe a mensagem do resultado. Nenhum token é exibido.
 */
final class MelhorEnvioStatusController {

  use StringTranslationTrait;

  /**
   * Cria o controlador.
   */
  public function __construct(
    private readonly MelhorEnvioConfig $config,
    private readonly MelhorEnvioTokenStoreInterface $tokens,
    private readonly DateFormatterInterface $dates,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Estado da conexão e ação de conectar ou reconectar.
   *
   * @return array<string, mixed>
   *   Render array da página.
   */
  public function page(): array {
    $environment = $this->config->environment();
    $tokens = $this->tokens->load($environment);
    $expiresAt = (int) ($tokens['expires_at'] ?? 0);
    $hasToken = is_array($tokens) && ($tokens['access_token'] ?? '') !== '';

    if (!$hasToken) {
      $connection = $this->t('Não conectada');
    }
    elseif ($expiresAt > $this->time->getRequestTime()) {
      $connection = $this->t('Conectada, válida até @date', ['@date' => $this->dates->format($expiresAt, 'short')]);
    }
    else {
      $connection = $this->t('Expirada em @date. Reconecte.', ['@date' => $this->dates->format($expiresAt, 'short')]);
    }

    $environmentLabel = $environment === MelhorEnvioEnvironment::Production
      ? $this->t('Produção')
      : $this->t('Sandbox (testes)');
    $scopes = $this->config->scopes() !== '' ? $this->config->scopes() : $this->t('Nenhuma');

    $connect = PortalLinkElement::build(
      $hasToken ? $this->t('Reconectar ao Melhor Envio') : $this->t('Conectar ao Melhor Envio'),
      Url::fromRoute('aculta_commerce_melhorenvio.oauth_authorize'),
      [
        'data-aculta-popup' => 'melhorenvio_autorizacao',
        'data-portal-ui' => 'button-primary',
      ],
    );
    $connect['#attached']['library'][] = 'aculta_portal/integration-popup';

    return [
      'status' => [
        '#type' => 'table',
        '#rows' => [
          [$this->t('Ambiente'), $environmentLabel],
          [$this->t('Conexão'), $connection],
          [$this->t('Permissões pedidas'), $scopes],
        ],
      ],
      'help' => [
        '#markup' => '<p>' . $this->t('A autorização abre numa janela do Melhor Envio. Ao concluir, a janela fecha e esta página mostra o resultado.') . '</p>',
      ],
      'connect' => $connect,
      '#cache' => ['max-age' => 0],
    ];
  }

}
