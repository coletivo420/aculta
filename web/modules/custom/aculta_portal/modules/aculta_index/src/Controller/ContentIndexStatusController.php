<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Controller;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read-only operational status for the content index.
 */
final class ContentIndexStatusController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ContentIndexWorkManager $workManager,
    private readonly ContentIndexProviderRegistry $providers,
  ) {}

  /**
   * Creates the controller from Drupal's service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('aculta_index.work_manager'),
      $container->get('aculta_index.provider_registry'),
    );
  }

  /**
   * Builds an operational summary without changing index state.
   */
  public function status(): array {
    $activeId = '';
    $active = NULL;
    if ($this->database->schema()->tableExists('aculta_index_state')) {
      $activeId = (string) $this->database->select('aculta_index_state', 'state')
        ->fields('state', ['active_generation'])
        ->condition('state_id', 1)
        ->execute()
        ->fetchField();
    }
    if ($activeId !== '' && $this->database->schema()->tableExists('aculta_index_generation')) {
      $active = $this->database->select('aculta_index_generation', 'generation')
        ->fields('generation', ['status', 'created', 'validated', 'activated'])
        ->condition('generation_id', $activeId)
        ->execute()
        ->fetchAssoc();
    }

    $queue = $this->database->schema()->tableExists('aculta_index_work')
      ? $this->workManager->getActivationGateState(array_keys($this->providers->all()))
      : ['ready' => TRUE, 'queued' => 0, 'processing' => 0, 'retry' => 0, 'dead' => 0];
    return [
      '#cache' => ['max-age' => 0],
      'aviso' => ['#markup' => '<p>' . $this->t('Consulta somente de leitura. Reconstruções e reenvios continuam disponíveis apenas por Drush.') . '</p>'],
      'geracao' => [
        '#type' => 'table',
        '#header' => [$this->t('Indicador'), $this->t('Estado')],
        '#rows' => [
          [$this->t('Geração ativa'), $activeId !== '' ? $activeId : $this->t('Nenhuma')],
          [$this->t('Situação'), $active['status'] ?? $this->t('Indisponível')],
          [$this->t('Última reconstrução concluída'), $this->formatTimestamp((int) ($active['validated'] ?? 0))],
          [$this->t('Ativada'), $this->formatTimestamp((int) ($active['activated'] ?? 0))],
        ],
      ],
      'pendencias' => [
        '#type' => 'table',
        '#header' => [$this->t('Pendência'), $this->t('Quantidade')],
        '#rows' => [
          [$this->t('Na fila'), (string) $queue['queued']],
          [$this->t('Em processamento'), (string) $queue['processing']],
          [$this->t('Aguardando nova tentativa'), (string) $queue['retry']],
          [$this->t('Falhas definitivas'), (string) $queue['dead']],
        ],
      ],
    ];
  }

  /**
   * Formats timestamps while keeping missing history explicit. */
  private function formatTimestamp(int $timestamp): string {
    return $timestamp > 0 ? $this->dateFormatter->format($timestamp, 'short') : (string) $this->t('Nunca');
  }

}
