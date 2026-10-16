<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_user\Moderation\ReportQueue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fila de denúncias do Fórum em /painel-administrativo/forum/denuncias (ACULTA Fórum, F5.1).
 *
 * Mostra o que foi denunciado, quantas denúncias cada conteúdo tem e o link para editar. A moderação (publicar,
 * ocultar ou manter) continua nos formulários nativos do Drupal; esta fila não altera nenhum conteúdo.
 */
final class ReportQueueController extends ControllerBase {

  public function __construct(
    private readonly ReportQueue $queue,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.report_queue'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Renderiza a fila.
   */
  public function view(): array {
    $resultado = $this->queue->items();
    $items = $resultado['items'];
    usort($items, fn(array $a, array $b) => $b['ultima'] <=> $a['ultima']);

    $build = [
      '#cache' => ['tags' => ['flagging_list', 'node_list', 'comment_list'], 'contexts' => ['user.permissions']],
    ];
    if ($items === []) {
      $build['vazio'] = ['#markup' => '<p>' . $this->t('Nenhuma denúncia pendente.') . '</p>'];
      return $build;
    }

    $rows = [];
    foreach ($items as $item) {
      $entity = $this->entityTypeManagerService->getStorage($item['entity_type'])->load($item['entity_id']);
      if ($entity === NULL) {
        continue;
      }
      $rows[] = [
        $item['entity_type'] === 'node' ? $this->t('Tópico') : $this->t('Comentário'),
        $this->labelOf($entity),
        $item['total'],
        $this->dateFormatter->format($item['ultima'], 'short'),
        ['data' => ['#type' => 'link', '#title' => $this->t('Abrir'), '#url' => $entity->toUrl()]],
      ];
    }

    $build['tabela'] = [
      '#type' => 'table',
      '#header' => [$this->t('Tipo'), $this->t('Conteúdo'), $this->t('Denúncias'), $this->t('Última denúncia'), $this->t('Ação')],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhuma denúncia pendente.'),
    ];
    if ($resultado['truncado']) {
      $build['aviso'] = ['#markup' => '<p>' . $this->t('A fila mostra só as denúncias mais recentes; há mais registros do que o limite de leitura.') . '</p>'];
    }
    return $build;
  }

  /**
   * Título do conteúdo denunciado (tópico ou comentário).
   */
  private function labelOf(object $entity): string {
    return method_exists($entity, 'label') ? (string) $entity->label() : '#' . $entity->id();
  }

}
