<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Controller;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Provider\BrevoEventStatus;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Estado de um envio: o registro e, para envios pelo Brevo, os eventos informados pelo provedor.
 *
 * Não mostra destinatário nem conteúdo. A consulta ao Brevo é feita a cada abertura da página (somente leitura).
 */
final class MailStatusController extends ControllerBase {

  /**
   * Janela de consulta dos eventos no Brevo, em dias.
   */
  private const EVENT_WINDOW_DAYS = 30;

  /**
   * Rótulos em português dos estados do registro.
   */
  private const STATUS_LABELS = [
    'queued' => 'Na fila',
    'processing' => 'Em processamento',
    'sent' => 'Aceito',
    'delivered' => 'Entregue',
    'deferred' => 'Adiado',
    'bounced' => 'Devolvido',
    'complained' => 'Reclamação',
    'failed' => 'Falhou',
    'cancelled' => 'Suspenso',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly BrevoEventStatus $brevo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get(BrevoEventStatus::class));
  }

  /**
   * Página de estado de um envio.
   */
  public function show(string $log_id): array {
    $log = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE)->load($log_id);
    if ($log === NULL) {
      throw new NotFoundHttpException();
    }
    $status = (string) $log->get('status')->value;
    $transport = (string) $log->get('transport')->value;
    $providerId = (string) $log->get('provider_message_id')->value;
    $build = [
      'titulo' => ['#markup' => '<h2>' . $this->t('Registro') . '</h2>'],
      'registro' => [
        '#type' => 'table',
        '#header' => [$this->t('Campo'), $this->t('Valor')],
        '#rows' => [
          [$this->t('Tipo'), (string) $log->get('type')->value],
          [$this->t('Estado no registro'), self::STATUS_LABELS[$status] ?? $status],
          [$this->t('Transporte'), $transport],
          [$this->t('Tentativas'), (string) $log->get('attempts')->value],
          [$this->t('Código de erro'), (string) $log->get('error_code')->value ?: '-'],
          [$this->t('ID no provedor'), $providerId ?: '-'],
          [$this->t('Aceito em'), $log->get('sent')->value ? date('Y-m-d H:i', (int) $log->get('sent')->value) : '-'],
        ],
      ],
    ];
    $build['titulo_provedor'] = ['#markup' => '<h2>' . $this->t('No provedor') . '</h2>'];
    $build['provedor'] = $this->providerSection($transport, $providerId);
    return $build;
  }

  /**
   * Eventos do provedor para envios pelo Brevo. Outros transportes não têm eventos consultáveis.
   */
  private function providerSection(string $transport, string $providerId): array {
    if ($transport !== 'brevo') {
      return ['#markup' => '<p>' . $this->t('Para este transporte o registro mostra a aceitação do servidor; o provedor não informa eventos por API.') . '</p>'];
    }
    if ($providerId === '') {
      return ['#markup' => '<p>' . $this->t('Sem ID do provedor: não há como consultar os eventos deste envio.') . '</p>'];
    }
    $events = $this->brevo->events($providerId, self::EVENT_WINDOW_DAYS);
    if ($events === NULL) {
      return ['#markup' => '<p>' . $this->t('Não foi possível consultar o Brevo agora. Tente de novo em instantes.') . '</p>'];
    }
    if ($events === []) {
      return ['#markup' => '<p>' . $this->t('Nenhum evento encontrado nos últimos @days dias.', ['@days' => self::EVENT_WINDOW_DAYS]) . '</p>'];
    }
    $rows = [];
    foreach ($events as $event) {
      $rows[] = [$event['event'], $event['date']];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Evento'), $this->t('Data')],
      '#rows' => $rows,
    ];
  }

}
