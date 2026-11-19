<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_cgu\ModerationScope;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Painel de moderação da fila unificada de denúncias do Fórum (ARQ-06F/G).
 *
 * @phpstan-type QueueItem array{target_type: string, target_id: int, motivos: list<string>, ultima: int, total: int}
 * @phpstan-type QueuePage array{items: list<QueueItem>, total_items: int, total_pages: int}
 */
final class ReportQueueController extends ControllerBase {

  public function __construct(
    private readonly ?object $ugcQueue = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->has('aculta_cgu.report_queue') ? $container->get('aculta_cgu.report_queue') : NULL,
    );
  }

  /**
   * Renderiza a fila unificada de denúncias ou redireciona no fallback legado.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   Requisição HTTP atual.
   *
   * @return array<string, mixed>|\Drupal\Core\Routing\LocalRedirectResponse
   *   Render array do painel de moderação ou resposta de redirecionamento.
   */
  public function view(?Request $request = NULL): array|LocalRedirectResponse {
    // Compatibilidade com teste unitário legado sem container ou serviço UGC.
    if ($this->ugcQueue === NULL || !method_exists($this->ugcQueue, 'getQueueItems')) {
      return new LocalRedirectResponse('/painel-administrativo/forum', 302);
    }

    $account = $this->currentUser();

    // Controle estrito: moderadores de outros purposes não acessam o Fórum.
    if (!ModerationScope::allows($account, 'forum')) {
      throw new AccessDeniedHttpException('Usuário sem permissão para moderar denúncias do Fórum.');
    }

    $page = $request ? max(0, $request->query->getInt('page', 0)) : 0;
    $limit = 25;

    $queueData = $this->readQueue($this->ugcQueue, $account, $page, $limit);

    $rows = [];
    $storage = $this->entityTypeManager();

    foreach ($queueData['items'] as $item) {
      $targetType = $item['target_type'];
      $targetId = $item['target_id'];

      // Resolução segura de acesso do alvo denunciado.
      if (!$storage->hasDefinition($targetType)) {
        $targetCell = ['data' => ['#plain_text' => sprintf('[%s #%d]', $targetType, $targetId)]];
      }
      else {
        $entity = $storage->getStorage($targetType)->load($targetId);
        if ($entity === NULL) {
          $targetCell = ['data' => ['#plain_text' => sprintf('[%s] (%s #%d)', $this->t('Conteúdo excluído'), $targetType, $targetId)]];
        }
        elseif (!$entity->access('view', $account)) {
          // Conteúdo restrito: não vaza label nem link sem acesso.
          $targetCell = ['data' => ['#plain_text' => sprintf('[%s] (%s #%d)', $this->t('Conteúdo restrito'), $targetType, $targetId)]];
        }
        else {
          $label = $entity->label() ?? sprintf('%s #%d', $targetType, $targetId);
          $isUnpublished = method_exists($entity, 'isPublished') && !$entity->isPublished();
          $displayText = $isUnpublished ? sprintf('%s (%s)', $label, $this->t('despublicado')) : (string) $label;

          if ($entity->hasLinkTemplate('canonical')) {
            try {
              $targetCell = ['data' => Link::fromTextAndUrl($displayText, $entity->toUrl())->toRenderable()];
            }
            catch (\Throwable) {
              $targetCell = ['data' => ['#plain_text' => $displayText]];
            }
          }
          else {
            $targetCell = ['data' => ['#plain_text' => $displayText]];
          }
        }
      }

      $motivosStr = implode(', ', $item['motivos']);
      $dataStr = gmdate('Y-m-d H:i:s', $item['ultima']);

      $rows[] = [
        $targetCell,
        ['data' => ['#plain_text' => $targetType]],
        ['data' => ['#plain_text' => $motivosStr]],
        ['data' => ['#plain_text' => (string) $item['total']]],
        ['data' => ['#plain_text' => $dataStr]],
      ];
    }

    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user.permissions', 'url.query_args:page'])
      ->addCacheTags(['aculta_cgu_report_list', 'flagging_list']);

    $build = [
      '#attached' => ['library' => ['aculta_user/report-queue']],
      '#cache' => [
        'contexts' => $cacheability->getCacheContexts(),
        'tags' => $cacheability->getCacheTags(),
        'max-age' => $cacheability->getCacheMaxAge(),
      ],
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'forum-report-queue'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Fila de Denúncias do Fórum (Growroom420)'),
      ],
      'info' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Exibindo denúncias pendentes de tópicos, postagens e comentários públicos. Total de conteúdos sinalizados: @total.', [
          '@total' => $queueData['total_items'],
        ]),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Conteúdo Denunciado'),
          $this->t('Tipo'),
          $this->t('Motivos Informados'),
          $this->t('Denúncias'),
          $this->t('Última Sinalização (UTC)'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Não há denúncias pendentes para o Fórum no momento.'),
      ],
    ];

    if ($queueData['total_pages'] > 1) {
      $build['pager'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'forum-report-pager'],
        'status' => [
          '#markup' => sprintf('<p>%s</p>', $this->t('Página @current de @total', [
            '@current' => $page + 1,
            '@total' => $queueData['total_pages'],
          ])),
        ],
      ];
      if ($page > 0) {
        $prevUrl = Url::fromRoute('aculta_user.denuncias_forum', [], ['query' => ['page' => $page - 1]]);
        $build['pager']['prev'] = Link::fromTextAndUrl($this->t('« Anterior'), $prevUrl)->toRenderable();
      }
      if ($page + 1 < $queueData['total_pages']) {
        $nextUrl = Url::fromRoute('aculta_user.denuncias_forum', [], ['query' => ['page' => $page + 1]]);
        $build['pager']['next'] = Link::fromTextAndUrl($this->t('Próxima »'), $nextUrl)->toRenderable();
      }
    }

    return $build;
  }

  /**
   * Lê a página da fila do serviço e valida o formato antes de usar.
   *
   * Entradas malformadas são descartadas; a validação fica nesta fronteira para
   * que o restante do painel trabalhe com tipos conhecidos.
   *
   * @phpstan-return QueuePage
   */
  private function readQueue(object $queue, AccountInterface $account, int $page, int $limit): array {
    $data = method_exists($queue, 'getQueueItems') ? $queue->getQueueItems($account, 'forum', 'pendente', $page, $limit) : [];
    $data = is_array($data) ? $data : [];

    $items = [];
    foreach (is_array($data['items'] ?? NULL) ? $data['items'] : [] as $raw) {
      if (!is_array($raw) || !is_string($raw['target_type'] ?? NULL) || !is_numeric($raw['target_id'] ?? NULL)) {
        continue;
      }
      $motivos = [];
      foreach (is_array($raw['motivos'] ?? NULL) ? $raw['motivos'] : [] as $motivo) {
        if (is_string($motivo)) {
          $motivos[] = $motivo;
        }
      }
      $items[] = [
        'target_type' => $raw['target_type'],
        'target_id' => (int) $raw['target_id'],
        'motivos' => $motivos,
        'ultima' => is_numeric($raw['ultima'] ?? NULL) ? (int) $raw['ultima'] : 0,
        'total' => is_numeric($raw['total'] ?? NULL) ? (int) $raw['total'] : 0,
      ];
    }

    return [
      'items' => $items,
      'total_items' => is_numeric($data['total_items'] ?? NULL) ? (int) $data['total_items'] : 0,
      'total_pages' => is_numeric($data['total_pages'] ?? NULL) ? (int) $data['total_pages'] : 0,
    ];
  }

}
