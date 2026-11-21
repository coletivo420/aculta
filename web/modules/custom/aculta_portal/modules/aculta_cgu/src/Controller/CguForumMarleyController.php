<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Controller;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\Form\CguReportDecisionForm;
use Drupal\aculta_cgu\ModerationScope;
use Drupal\aculta_cgu\Service\CguReportQueue;
use Drupal\aculta_cgu\Service\ReportService;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_forum\Service\ForumMarleyTopicActions;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Serves the CGU forum queue and real decision form inside the Marley frontend.
 *
 * This controller does not implement a second vote/decision engine. All writes
 * remain in CguReportDecisionForm -> ReportService, with Form API CSRF.
 */
final class CguForumMarleyController extends ControllerBase {

  private const PAGE_SIZE = 25;

  public function __construct(
    private readonly CguReportQueue $reportQueue,
    private readonly ReportService $reports,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly DateFormatterInterface $dates,
    private readonly FormBuilderInterface $forms,
    private readonly DomainSiteManagerInterface $sites,
    private readonly ?ForumMarleyTopicActions $forumTopicActions = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('aculta_cgu.report_queue'),
      $container->get('aculta_cgu.report_service'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('form_builder'),
      $container->get('aculta_portal.domain_site'),
      $container->has('aculta_forum.marley_topic_actions')
        ? $container->get('aculta_forum.marley_topic_actions')
        : NULL,
    );
  }

  /**
   * The Forum tab can never inherit access to other CGU purposes.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated() && ModerationScope::allows($account, 'forum'))
      ->cachePerPermissions()
      ->cachePerUser();
  }

  /**
   * Only an authorized forum report with a viewable forum target can be read.
   */
  public function reportAccess(AccountInterface $account, ?CguReport $aculta_cgu_report = NULL): AccessResultInterface {
    $permission = $this->access($account);
    if (!$permission->isAllowed() || $aculta_cgu_report === NULL
      || (string) ($aculta_cgu_report->get('purpose')->getString() ?: '') !== 'forum') {
      $forbidden = AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
      if ($aculta_cgu_report !== NULL) {
        $forbidden->addCacheableDependency($aculta_cgu_report);
      }
      return $forbidden;
    }

    $target = $this->target($aculta_cgu_report);
    if ($target === NULL) {
      return AccessResult::forbidden()->cachePerPermissions()->cachePerUser()
        ->addCacheableDependency($aculta_cgu_report);
    }
    $targetView = $this->targetViewAccess($target, $account);
    $finalAccess = $permission->andIf($targetView);
    if ($finalAccess instanceof AccessResult) {
      $finalAccess->addCacheableDependency($target)
        ->addCacheableDependency($aculta_cgu_report);
    }
    return $finalAccess;
  }

  /**
   * The scoped, access-filtered report queue rendered with the Marley SDC.
   *
   * @return array<string, mixed>
   *   Frontend render array.
   */
  public function queue(Request $request): array {
    $statusOptions = [
      ReportService::STATUS_PENDING => $this->t('Pendentes'),
      ReportService::STATUS_REVIEW => $this->t('Em análise'),
      ReportService::STATUS_UPHELD => $this->t('Procedentes'),
      ReportService::STATUS_REJECTED => $this->t('Improcedentes'),
    ];
    $requested = $request->query->get('status', ReportService::STATUS_PENDING);
    $status = isset($statusOptions[(string) $requested])
      ? (string) $requested : ReportService::STATUS_PENDING;
    $page = min(1000, max(0, $request->query->getInt('page', 0)));
    $data = $this->reportQueue->getQueueItems($this->account, 'forum', $status, $page, self::PAGE_SIZE);
    $reportStorage = $this->entities->getStorage('aculta_cgu_report');
    $rows = [];
    $selected = NULL;

    foreach ($data['items'] as $item) {
      $type = $item['target_type'];
      $id = $item['target_id'];
      $entity = $this->loadForumTarget($type, $id);
      if ($entity === NULL || !$this->targetViewAccess($entity, $this->account)->isAllowed()) {
        continue;
      }
      // The queue may include flags not yet migrated to CGU. Such legacy
      // signals are displayed but may NOT be decided without a CGU report.
      $ids = $reportStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('purpose', 'forum')
        ->condition('target_type', $type)
        ->condition('target_id', $id)
        ->condition('status', $status)
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();
      $report = $ids === [] ? NULL : $reportStorage->load(reset($ids));
      $action = ['#plain_text' => (string) $this->t('Aguardando registro CGU')];
      if ($report instanceof CguReport && $this->reportAccess($this->account, $report)->isAllowed()) {
        $link = Url::fromRoute('aculta_cgu.forum_marley_decide', ['aculta_cgu_report' => (string) $report->id()]);
        $action = [
          '#theme' => 'portal_component',
          '#name' => 'moderation-action',
          '#props' => [
            'label' => (string) $this->t('Analisar caso'),
            'url' => $link->toString(),
            'icon' => 'shield-check',
            'tone' => 'primary',
          ],
        ];
      }

      $label = (string) ($entity->label() ?: sprintf('%s #%d', $type, $id));
      $rows[] = [
        'data' => [
          ['data' => $this->targetLabel($entity, $label)],
          ['data' => ['#plain_text' => (string) $item['total']]],
          ['data' => ['#plain_text' => implode(', ', $item['motivos']) ?: '-']],
          ['data' => ['#plain_text' => $item['ultima'] > 0
            ? $this->dates->format($item['ultima'], 'short') : '-']],
          ['data' => $action],
        ],
      ];
      if ($selected === NULL) {
        $selected = ['label' => $label, 'total' => (int) $item['total'], 'motivos' => $item['motivos'], 'action' => $action];
      }
    }

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Conteúdo sinalizado'),
        $this->t('Denúncias'),
        $this->t('Motivos'),
        $this->t('Última ocorrência'),
        $this->t('Examinar'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Não há casos acessíveis nesta página para o filtro selecionado.'),
    ];

    $filters = [];
    foreach ($statusOptions as $code => $label) {
      $filters[] = [
        'label' => (string) $label,
        'url' => Url::fromRoute('aculta_cgu.forum_marley_queue', [], ['query' => ['status' => $code]])->toString(),
        'is_active' => $status === $code,
      ];
    }

    $aside = $selected === NULL
      ? ['#plain_text' => (string) $this->t('Selecione outro filtro para consultar os casos disponíveis.')]
      : [
        '#type' => 'container',
        'target' => ['#plain_text' => $selected['label']],
        'total' => ['#plain_text' => (string) $this->formatPlural($selected['total'], '1 denúncia', '@count denúncias')],
        'motivos' => ['#plain_text' => implode(', ', $selected['motivos']) ?: '-'],
        'action' => $selected['action'],
      ];

    $footer = [
      '#type' => 'container',
      'notice' => ['#plain_text' => (string) $this->t('A decisão sobre uma denúncia não despublica o conteúdo automaticamente.')],
    ];
    // Avoid claiming an exact count for access-restricted targets. The queue
    // service groups by target, but target access is rechecked above.
    if ($page > 0) {
      $footer['previous'] = [
        '#type' => 'link',
        '#title' => $this->t('Página anterior'),
        '#url' => Url::fromRoute('aculta_cgu.forum_marley_queue', [], ['query' => ['status' => $status, 'page' => $page - 1]]),
      ];
    }
    if (count($data['items']) === self::PAGE_SIZE && $rows !== []) {
      $footer['next'] = [
        '#type' => 'link',
        '#title' => $this->t('Próxima página'),
        '#url' => Url::fromRoute('aculta_cgu.forum_marley_queue', [], ['query' => ['status' => $status, 'page' => $page + 1]]),
      ];
    }

    return $this->workspace(
      (string) $this->t('Fórum - fila de revisão'),
      (string) $this->t('Relatos comunitários autorizados, com decisões auditadas pelo CGU.'),
      [
        'summary' => $this->component('statistics-moderation-summary', [
          'title' => (string) $this->t('Revisão comunitária'),
          'note' => (string) $this->t('Somente casos visíveis neste recorte da fila.'),
          'rows' => [
            ['label' => (string) $this->t('Casos visíveis'), 'value' => (string) count($rows)],
            ['label' => (string) $this->t('Estado'), 'value' => (string) $statusOptions[$status]],
          ],
        ]),
        'filters' => $this->component('filter-chips', ['items' => $filters]),
        'primary' => $this->component('studio-listing-table', [], ['content' => $table]),
        'aside' => $this->component('surface-panel', [
          'tone' => 'raised',
          'header_variant' => 'subtle',
          'show_header_placeholder' => FALSE,
        ], [
          'header' => ['#plain_text' => (string) $this->t('Inspeção contextual')],
          'content' => $aside,
        ]),
        'footer' => $footer,
      ],
    );
  }

  /**
   * Real CGU decision Form API rendered in a Marley inspector.
   *
   * @return array<string, mixed>
   *   Render array with a POST+CSRF form owned by CGU.
   */
  public function report(CguReport $aculta_cgu_report): array {
    if (!$this->reportAccess($this->account, $aculta_cgu_report)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    $target = $this->target($aculta_cgu_report);
    if ($target === NULL) {
      throw new AccessDeniedHttpException();
    }
    // Form API builds and processes the form on the same frontend route.
    // On submit, CguReportDecisionForm delegates to ReportService.
    $form = $this->forms->getForm(CguReportDecisionForm::class, $aculta_cgu_report);
    $label = (string) ($target->label() ?: $this->t('Publicação do Fórum'));
    $topicActions = $target instanceof NodeInterface && $this->forumTopicActions !== NULL
      ? $this->forumTopicActions->buildActions($target, $this->account)
      : [];

    $reportId = (string) ($aculta_cgu_report->id() ?? '');
    return $this->workspace(
      (string) $this->t('Decidir denúncia #@id', ['@id' => $reportId]),
      (string) $this->t('A denúncia é avaliada separadamente da publicação.'),
      [
        'primary' => $this->component('surface-panel', [
          'tone' => 'raised',
          'header_variant' => 'subtle',
          'show_header_placeholder' => FALSE,
        ], [
          'header' => ['#plain_text' => (string) $this->t('Conteúdo denunciado')],
          'content' => [
            'label' => $this->targetLabel($target, $label),
            'note' => ['#plain_text' => (string) $this->t('Nenhuma ação sobre a visibilidade do conteúdo ocorre ao decidir o relato.')],
            'topic_controls' => $topicActions === [] ? [] : [
              '#type' => 'container',
              '#attributes' => ['class' => ['studio-marley-workspace__control-row']],
              'title' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $this->t('Controles do tópico')],
              'links' => ['#theme' => 'item_list', '#items' => $topicActions],
            ],
          ],
        ]),
        'aside' => $this->component('surface-panel', [
          'tone' => 'raised',
          'header_variant' => 'subtle',
          'show_header_placeholder' => FALSE,
        ], [
          'header' => ['#plain_text' => (string) $this->t('Parecer e decisão do CGU')],
          'content' => $form,
        ]),
        'footer' => [
          '#type' => 'link',
          '#title' => $this->t('Voltar à fila de revisão'),
          '#url' => Url::fromRoute('aculta_cgu.forum_marley_queue'),
        ],
      ],
    );
  }

  /**
   * Enforces real origin and view access before exposing case metadata.
   */
  private function target(CguReport $report): ?EntityInterface {
    $type = (string) ($report->get('target_type')->getString() ?: '');
    $id = (int) ($report->get('target_id')->getString() ?: '0');
    return $this->loadForumTarget($type, $id);
  }

  /**
   * Loads only current Forum-origin content, excluding archives and consumers.
   */
  private function loadForumTarget(string $type, int $id): ?EntityInterface {
    if (!in_array($type, ['node', 'comment', 'aculta_forum_post'], TRUE)
      || $id <= 0 || !$this->entities->hasDefinition($type)) {
      return NULL;
    }
    $entity = $this->entities->getStorage($type)->load($id);
    if (!$entity instanceof EntityInterface) {
      return NULL;
    }

    $topic = $this->forumTopic($entity);
    if (!$topic instanceof NodeInterface || !$topic->hasField('field_domain_source')
      || $topic->get('field_domain_source')->isEmpty()
      || $this->sites->getSiteKeyValueForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum') {
      return NULL;
    }
    // A report purpose label alone never authorizes a host from LMS/Wiki,
    // Revista or legacy Growroom to enter this forum queue.
    try {
      return $this->reports->resolvePurpose($entity) === 'forum' ? $entity : NULL;
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Returns the Forum topic underlying a topic/reply/comment.
   */
  private function forumTopic(EntityInterface $entity): ?NodeInterface {
    if ($entity instanceof NodeInterface) {
      return $entity->bundle() === 'aculta_forum_topic' ? $entity : NULL;
    }
    if ($entity instanceof CommentInterface) {
      $host = $entity->getCommentedEntity();
      return $host instanceof EntityInterface && !($host instanceof CommentInterface)
        ? $this->forumTopic($host) : NULL;
    }
    if ($entity->getEntityTypeId() === 'aculta_forum_post'
      && $entity instanceof ContentEntityInterface && $entity->hasField('topic')) {
      $topic = $entity->get('topic')->entity;
      return $topic instanceof NodeInterface && $topic->bundle() === 'aculta_forum_topic'
        ? $topic : NULL;
    }
    return NULL;
  }

  /**
   * Performs both target and host entity access in the owner Forum domain.
   */
  private function targetViewAccess(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    $topic = $this->forumTopic($entity);
    if (!$topic instanceof NodeInterface) {
      return AccessResult::forbidden()->cachePerUser();
    }
    $access = $this->sites->runInSite('forum', static fn (): AccessResultInterface =>
      $entity->access('view', $account, TRUE)->andIf($topic->access('view', $account, TRUE)),
    );
    return $access instanceof AccessResultInterface
      ? $access
      : AccessResult::forbidden()->cachePerUser();
  }

  /**
   * Creates a canonical owner-domain URL only when it is viewable.
   *
   * @return array<string, mixed>
   *   Safe link or escaped label.
   */
  private function targetLabel(EntityInterface $entity, string $label): array {
    if ($entity->hasLinkTemplate('canonical')) {
      try {
        $url = $entity->toUrl();
        if ($url->isRouted()) {
          /** @var array<string, mixed> $routeParams */
          $routeParams = $url->getRouteParameters();
          $forumUrl = $this->sites->routeUrl('forum', $url->getRouteName(), $routeParams);
          if ($forumUrl instanceof Url) {
            return ['#type' => 'link', '#title' => $label, '#url' => $forumUrl];
          }
        }
      }
      catch (\Throwable) {
        // Missing canonical route: display only the escaped entity label.
      }
    }
    return ['#plain_text' => $label];
  }

  /**
   * SDC from the theme, with all data and actions supplied by their owner.
   *
   * @param array<string, mixed> $slots
   *   Named render arrays for the existing Marley SDC.
   *
   * @return array<string, mixed>
   *   Uncached user-scoped workspace.
   */
  private function workspace(string $title, string $description, array $slots): array {
    $slots['navigation'] = [
      '#type' => 'link',
      '#title' => $this->t('Área Fórum'),
      '#url' => Url::fromRoute('aculta_studio.area', ['aba' => 'forum']),
      '#attributes' => ['aria-current' => 'page'],
    ];
    return $this->component('studio-marley-workspace', [
      'dom_id' => 'marley-forum-cgu',
      'area' => 'forum',
      'title' => $title,
      'description' => $description,
      'preview_note' => '',
    ], $slots) + [
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'domain', 'url.query_args'],
        'tags' => ['aculta_cgu_report_list', 'aculta_forum_post_list'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Renders the existing SDC with its owner-provided props and slots.
   *
   * @param array<string, mixed> $props
   *   Component properties.
   * @param array<string, mixed> $slots
   *   Component slots.
   *
   * @return array<string, mixed>
   *   Portal component render array.
   */
  private function component(string $name, array $props = [], array $slots = []): array {
    return [
      '#theme' => 'portal_component',
      '#name' => $name,
      '#props' => $props,
      '#slots' => $slots,
    ];
  }

}
