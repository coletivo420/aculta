<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Drupal\aculta_ugc\ModerationScope;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Painel de moderação da fila unificada de denúncias do Fórum (ARQ-06F/G).
 */
final class ReportQueueController extends ControllerBase {

  public function __construct(
    private readonly mixed $ugcQueue = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->has('aculta_ugc.report_queue') ? $container->get('aculta_ugc.report_queue') : NULL,
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

    $queueData = $this->ugcQueue->getQueueItems($account, 'forum', 'pendente', $page, $limit);

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

    $build = [
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

    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user.permissions', 'url.query_args:page'])
      ->addCacheTags(['aculta_ugc_report_list', 'flagging_list']);
    $cacheability->applyTo($build);

    return $build;
  }

}
