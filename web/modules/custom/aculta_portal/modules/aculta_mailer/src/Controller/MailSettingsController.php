<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configurações do envio: estado das credenciais do transporte, sem exibir valores.
 *
 * A alteração das chaves é feita no painel "Credenciais do ambiente" do aculta_portal, que já grava no arquivo de
 * credenciais do servidor. Esta página só mostra o estado e leva a ele.
 */
final class MailSettingsController extends ControllerBase {

  /**
   * Credencial do transporte Brevo (ver docs/TRANSPORTS.md).
   */
  private const BREVO = 'BREVO_API_KEY';

  public function __construct(
    private readonly ?object $secrets,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // O aculta_portal não é dependência deste módulo (evita ciclo). Sem ele, a página informa a indisponibilidade.
    $secrets = $container->has('aculta_portal.secrets_manager') ? $container->get('aculta_portal.secrets_manager') : NULL;
    return new static($secrets);
  }

  /**
   * Página de configurações.
   */
  public function settings(): array {
    if ($this->secrets === NULL) {
      return ['#markup' => '<p>' . $this->t('Gerenciador de credenciais indisponível: o módulo aculta_portal não está habilitado.') . '</p>'];
    }
    $present = $this->secrets->value(self::BREVO) !== NULL;
    $rows = [
      [
        self::BREVO,
        $present ? $this->t('configurada') : $this->t('não configurada'),
        $this->t('Transporte Brevo ainda não está ativo: o envio usa o padrão configurado.'),
      ],
    ];
    $build = [
      'storage' => ['#markup' => '<p>' . $this->t('Armazenamento neste ambiente: @mode.', ['@mode' => $this->secrets->storageMode()]) . '</p>'],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Credencial'), $this->t('Estado'), $this->t('Uso')],
        '#rows' => $rows,
      ],
    ];
    $build['delivery'] = [
      '#type' => 'link',
      '#title' => $this->t('Entrega, endereço de retorno, alertas e resumo semanal'),
      '#url' => Url::fromRoute('aculta_mailer.delivery_settings'),
    ];
    $build['monitor'] = [
      '#type' => 'link',
      '#title' => $this->t('Monitoramento IMAP (bounces e DMARC)'),
      '#url' => Url::fromRoute('aculta_mailer.imap_settings'),
    ];
    if ($this->currentUser()->hasPermission('administer aculta secrets')) {
      $build['change'] = [
        '#type' => 'link',
        '#title' => $this->t('Alterar ou remover a chave'),
        '#url' => Url::fromRoute('aculta_portal.secrets_edit', ['name' => self::BREVO]),
      ];
    }
    else {
      $build['no_access'] = ['#markup' => '<p>' . $this->t('Para alterar a chave é preciso a permissão de administrar credenciais.') . '</p>'];
    }
    return $build;
  }

}
