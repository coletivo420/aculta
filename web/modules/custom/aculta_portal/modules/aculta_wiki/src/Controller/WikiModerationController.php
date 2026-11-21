<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Controller;

use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\Form\CguReportDecisionForm;
use Drupal\aculta_cgu\Service\CguReportQueue;
use Drupal\aculta_cgu\Service\ReportService;
use Drupal\aculta_wiki\Access\WikiModerationAccess;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\aculta_wiki\Service\WikiEditorialQueue;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Wiki-owned moderation UI in WE Sativa, never in the Forum/Marley workspace.
 *
 * Decisions remain in WikiProposalDecisionForm, Core Content Moderation and
 * CguReportDecisionForm -> ReportService. This controller never writes entities.
 */
final class WikiModerationController extends ControllerBase {

  private const PAGE_SIZE = 25;

  public function __construct(
    private readonly WikiEditorialQueue $editorialQueue,
    private readonly CguReportQueue $reportQueue,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $moderator,
    private readonly WikiModerationAccess $access,
    private readonly FormBuilderInterface $forms,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('aculta_wiki.editorial_queue'),
      $container->get('aculta_cgu.report_queue'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_wiki.moderation_access'),
      $container->get('form_builder'),
    );
  }

  /**
   * Purpose-specific entry: only sections granted to the active account.
   *
   * @return array<string, mixed>
   *   SDC-powered private workspace.
   */
  public function overview(): array {
    $this->requireAccess($this->access->overviewAccess($this->moderator));

    $items = [];
    $sections = [
      'revisions' => [
        'access' => $this->access->revisionsAccess($this->moderator),
        'route' => 'aculta_wiki.moderation_revisions',
        'title' => (string) $this->t('Verbetes em revisão'),
        'summary' => (string) $this->t('Revisões aguardando a decisão editorial no workflow da Wiki.'),
        'icon' => 'entry',
      ],
      'proposals' => [
        'access' => $this->access->proposalsAccess($this->moderator),
        'route' => 'aculta_wiki.moderation_proposals',
        'title' => (string) $this->t('Propostas de contribuição'),
        'summary' => (string) $this->t('Sugestões de alterações e fontes para verbetes já existentes.'),
        'icon' => 'proposal',
      ],
      'reports' => [
        'access' => $this->access->reportsAccess($this->moderator),
        'route' => 'aculta_wiki.moderation_reports',
        'title' => (string) $this->t('Denúncias sobre a Wiki'),
        'summary' => (string) $this->t('Sinalizações comunitárias decididas pelo CGU sem alterar publicações automaticamente.'),
        'icon' => 'report',
      ],
    ];
    foreach ($sections as $section) {
      if (!$section['access']->isAllowed()) {
        continue;
      }
      $url = Url::fromRoute($section['route']);
      $items[] = $this->case(
        $section['title'],
        $section['icon'],
        'review',
        (string) $this->t('Acessível'),
        $section['summary'],
        Link::fromTextAndUrl($this->t('Abrir fila'), $url)->toRenderable(),
      );
    }

    return $this->workspace(
      (string) $this->t('Moderação da Wiki'),
      (string) $this->t('Cada equipe analisa somente os conteúdos e denúncias de sua competência.'),
      'home',
      $this->caseList($items, (string) $this->t('Nenhuma fila editorial está disponível para esta conta.')),
    );
  }

  /**
   * Only entity-authorized proposals, with Wiki-owned decision forms.
   *
   * @return array<string, mixed>
   *   Dedicated WE Sativa proposal queue.
   */
  public function proposals(Request $request): array {
    $this->requireAccess($this->access->proposalsAccess($this->moderator));
    $rawCursor = $request->query->get('wiki_proposal_cursor');
    $cursor = is_string($rawCursor) ? $rawCursor : NULL;
    $page = $this->editorialQueue->proposals($cursor);

    $items = [];
    foreach ($page->getItems() as $proposal) {
      if (!$proposal instanceof WikiProposal && !$proposal instanceof Contribution) {
        continue;
      }
      $view = $proposal->access('view', $this->moderator, TRUE);
      $update = $proposal->access('update', $this->moderator, TRUE);
      if (!$view->isAllowed() || !$update->isAllowed()) {
        continue;
      }
      if ($proposal instanceof Contribution && ($proposal->get('source')->getString() !== 'wiki'
        || $proposal->get('purpose')->getString() !== 'wiki'
        || $proposal->get('source_module')->getString() !== 'aculta_wiki')) {
        continue;
      }
      $url = Url::fromRoute('aculta_wiki.proposal_decide', ['proposal' => $proposal->id()]);
      if (!$url->access($this->moderator)) {
        continue;
      }
      $items[] = $this->case(
        (string) ($proposal->label() ?: $this->t('Proposta editorial')),
        'proposal',
        $proposal->get('state')->getString() === 'needs_changes' ? 'changes' : 'pending',
        (string) $this->t('Aguardando decisão'),
        (string) $this->t('A proposta será analisada sem modificar o verbete antes da aprovação.'),
        Link::fromTextAndUrl($this->t('Avaliar proposta'), $url)->toRenderable(),
      );
    }

    $content = $this->caseList($items, (string) $this->t('Nenhuma proposta da Wiki disponível para revisão.'));
    if ($page->getNextCursor() !== NULL && $items !== []) {
      $content['next_page'] = [
        '#type' => 'link',
        '#title' => $this->t('Mais propostas'),
        '#url' => Url::fromRoute('aculta_wiki.moderation_proposals', [], ['query' => ['wiki_proposal_cursor' => $page->getNextCursor()]]),
      ];
    }
    return $this->workspace(
      (string) $this->t('Propostas para a Wiki'),
      (string) $this->t('A decisão usa o formulário Wiki e verifica novamente direitos, estado e revisão-base.'),
      'proposals',
      $content,
    );
  }

  /**
   * Only latest revisions and Core transitions validated by Wiki owner.
   *
   * @return array<string, mixed>
   *   Dedicated editorial queue.
   */
  public function revisions(Request $request): array {
    $this->requireAccess($this->access->revisionsAccess($this->moderator));
    $rawCursor = $request->query->get('wiki_revision_cursor');
    $cursor = is_string($rawCursor) ? $rawCursor : NULL;
    $page = $this->editorialQueue->entryRevisions($cursor);

    $items = [];
    foreach ($page->getItems() as $revision) {
      if (!$revision instanceof NodeInterface || !$this->access->isWikiHost($revision)) {
        continue;
      }
      $revisionAccess = $revision->access('view revision', $this->moderator, TRUE);
      $update = $revision->access('update', $this->moderator, TRUE);
      if (!$revisionAccess->isAllowed() || !$update->isAllowed()) {
        continue;
      }
      $id = $revision->id();
      $vid = $revision->getRevisionId();
      if (!is_numeric($id) || !is_numeric($vid)) {
        continue;
      }
      $url = Url::fromRoute('entity.node.revision', ['node' => $id, 'node_revision' => $vid]);
      if (!$url->access($this->moderator)) {
        continue;
      }
      $items[] = $this->case(
        (string) ($revision->label() ?: $this->t('Verbete em revisão')),
        'entry',
        'review',
        (string) $this->t('Revisão editorial'),
        (string) $this->t('Confira fontes e versão antes de usar as transições do Content Moderation.'),
        Link::fromTextAndUrl($this->t('Examinar revisão'), $url)->toRenderable(),
      );
    }
    $content = $this->caseList($items, (string) $this->t('Não há revisões editoriais autorizadas.'));
    if ($page->getNextCursor() !== NULL && $items !== []) {
      $content['next_page'] = [
        '#type' => 'link',
        '#title' => $this->t('Próximas revisões'),
        '#url' => Url::fromRoute('aculta_wiki.moderation_revisions', [], ['query' => ['wiki_revision_cursor' => $page->getNextCursor()]]),
      ];
    }
    return $this->workspace(
      (string) $this->t('Verbetes em revisão'),
      (string) $this->t('Somente as revisões vigentes autorizadas pela Wiki e pelo workflow.'),
      'revisions',
      $content,
    );
  }

  /**
   * CGU records scoped strictly to Wiki; no Forum flag reconciliation.
   *
   * @return array<string, mixed>
   *   Private and access-filtered reports.
   */
  public function reports(Request $request): array {
    $this->requireAccess($this->access->reportsAccess($this->moderator));
    $statuses = [
      ReportService::STATUS_PENDING => (string) $this->t('Pendente'),
      ReportService::STATUS_REVIEW => (string) $this->t('Em análise'),
      ReportService::STATUS_UPHELD => (string) $this->t('Procedente'),
      ReportService::STATUS_REJECTED => (string) $this->t('Improcedente'),
    ];
    $rawStatus = $request->query->get('status');
    $status = is_string($rawStatus) && isset($statuses[$rawStatus])
      ? $rawStatus : ReportService::STATUS_PENDING;
    $page = min(1000, max(0, $request->query->getInt('page', 0)));
    $data = $this->reportQueue->getQueueItems($this->moderator, 'wiki', $status, $page, self::PAGE_SIZE);
    $items = [];

    foreach ($data['items'] as $candidate) {
      if ($candidate['purpose'] !== 'wiki'
        || !in_array($candidate['target_type'], ['node', 'comment'], TRUE)) {
        continue;
      }
      $storage = $this->entities->getStorage('aculta_cgu_report');
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('purpose', 'wiki')
        ->condition('target_type', $candidate['target_type'])
        ->condition('target_id', $candidate['target_id'])
        ->condition('status', $status)
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();
      if ($ids === []) {
        continue;
      }
      $report = $storage->load(reset($ids));
      if (!$report instanceof CguReport
        || !$this->access->reportAccess($this->moderator, $report)->isAllowed()) {
        continue;
      }
      $target = $this->loadTarget($report);
      if ($target === NULL) {
        continue;
      }
      $url = Url::fromRoute('aculta_wiki.moderation_report_decide', ['aculta_cgu_report' => $report->id()]);
      if (!$url->access($this->moderator)) {
        continue;
      }
      $items[] = $this->case(
        (string) ($target->label() ?: $this->t('Conteúdo sinalizado')),
        'report',
        in_array($status, [ReportService::STATUS_UPHELD, ReportService::STATUS_REJECTED], TRUE) ? 'decided' : 'pending',
        $statuses[$status],
        (string) $this->formatPlural((int) $candidate['total'], '1 denúncia sobre este conteúdo', '@count denúncias sobre este conteúdo'),
        Link::fromTextAndUrl($this->t('Examinar caso CGU'), $url)->toRenderable(),
      );
    }

    $content = $this->caseList($items, (string) $this->t('Não há denúncias acessíveis nesta página.'));
    $filterLinks = ['#type' => 'container', '#attributes' => ['class' => ['wiki-moderation-filters']]];
    foreach ($statuses as $key => $label) {
      $link = Link::fromTextAndUrl($label, Url::fromRoute('aculta_wiki.moderation_reports', [], ['query' => ['status' => $key]]))->toRenderable();
      if ($status === $key) {
        $attributes = $link['#attributes'] ?? NULL;
        $attributes = is_array($attributes) ? $attributes : [];
        $attributes['aria-current'] = 'page';
        $link['#attributes'] = $attributes;
      }
      $filterLinks[$key] = $link;
    }
    if ($page > 0) {
      $content['previous_page'] = [
        '#type' => 'link',
        '#title' => $this->t('Página anterior'),
        '#url' => Url::fromRoute('aculta_wiki.moderation_reports', [], ['query' => ['status' => $status, 'page' => $page - 1]]),
      ];
    }
    if (count($data['items']) === self::PAGE_SIZE && $items !== []) {
      $content['next_page'] = [
        '#type' => 'link',
        '#title' => $this->t('Próxima página'),
        '#url' => Url::fromRoute('aculta_wiki.moderation_reports', [], ['query' => ['status' => $status, 'page' => $page + 1]]),
      ];
    }
    $filters = [
      '#theme' => 'portal_component',
      '#name' => 'wiki-moderation-filters',
      '#props' => [
        'title' => (string) $this->t('Filtrar denúncias por situação'),
      ],
      '#slots' => ['options' => $filterLinks],
    ];
    $result = $this->workspace(
      (string) $this->t('Denúncias da Wiki'),
      (string) $this->t('Pareceres do CGU não substituem o workflow de publicação editorial.'),
      'reports',
      ['filters' => $filters, 'cases' => $content],
    );
    return $result;
  }

  /**
   * Real CGU Form API on a Wiki-only route with a separately checked target.
   *
   * @return array<string, mixed>
   *   Wiki SDC embedding the owner CGU form.
   */
  public function decision(CguReport $aculta_cgu_report): array {
    $this->requireAccess($this->access->reportAccess($this->moderator, $aculta_cgu_report));
    $target = $this->loadTarget($aculta_cgu_report);
    if ($target === NULL) {
      throw new AccessDeniedHttpException();
    }
    $form = $this->forms->getForm(CguReportDecisionForm::class, $aculta_cgu_report);
    $content = [
      '#theme' => 'portal_component',
      '#name' => 'wiki-moderation-decision',
      '#props' => [
        'title' => (string) $this->t('Avaliação de denúncia'),
        'note' => (string) $this->t('A decisão registra um parecer CGU e não despublica automaticamente o verbete.'),
      ],
      '#slots' => [
        'context' => ['#plain_text' => (string) ($target->label() ?: $this->t('Conteúdo da Wiki'))],
        'form' => $form,
      ],
    ];
    return $this->workspace(
      (string) $this->t('Decisão CGU da Wiki'),
      (string) $this->t('Somente casos e entidades acessíveis no purpose Wiki.'),
      'reports',
      $content,
    );
  }

  /**
   * Returns only authorized Wiki report targets, never raw PII.
   */
  private function loadTarget(CguReport $report): NodeInterface|CommentInterface|null {
    $type = $report->get('target_type')->getString();
    $raw = $report->get('target_id')->value;
    if (!in_array($type, ['node', 'comment'], TRUE) || !is_numeric($raw)) {
      return NULL;
    }
    $target = $this->entities->getStorage($type)->load((int) $raw);
    if ($target instanceof NodeInterface && $this->access->isWikiHost($target)) {
      return $target;
    }
    if ($target instanceof CommentInterface
      && $target->getCommentedEntity() instanceof NodeInterface
      && $this->access->isWikiHost($target->getCommentedEntity())) {
      return $target;
    }
    return NULL;
  }

  /**
   * Builds a Wiki-specific, accessible case card.
   *
   * @param array<mixed> $action
   *   Authorized Link render array.
   *
   * @return array<string, mixed>
   *   Case SDC render array.
   */
  private function case(string $title, string $kind, string $status, string $statusLabel, string $summary, array $action): array {
    return [
      '#theme' => 'portal_component',
      '#name' => 'wiki-moderation-case',
      '#props' => [
        'title' => $title,
        'kind' => $kind,
        'status' => $status,
        'status_label' => $statusLabel,
        'summary' => $summary,
      ],
      '#slots' => ['actions' => $action],
    ];
  }

  /**
   * Render list without exposing inaccessible item counts.
   *
   * @param array<int, array<string, mixed>> $items
   *   Authorized case render arrays.
   *
   * @return array<string, mixed>
   *   Empty state or cases.
   */
  private function caseList(array $items, string $empty): array {
    return $items === []
      ? [
        '#theme' => 'portal_component',
        '#name' => 'empty-state',
        '#slots' => ['message' => ['#plain_text' => $empty]],
      ]
      : [
        '#type' => 'container',
        '#attributes' => ['class' => ['wiki-moderation-cases']],
        'items' => $items,
      ];
  }

  /**
   * Shell and tabs are served only for scopes held by the current account.
   *
   * @param array<string, mixed> $content
   *   Render array for the active queue.
   *
   * @return array<string, mixed>
   *   Page render array.
   */
  private function workspace(string $title, string $description, string $active, array $content): array {
    $routes = [
      'home' => ['aculta_wiki.moderation_home', (string) $this->t('Visão geral'), $this->access->overviewAccess($this->moderator)],
      'revisions' => ['aculta_wiki.moderation_revisions', (string) $this->t('Verbetes'), $this->access->revisionsAccess($this->moderator)],
      'proposals' => ['aculta_wiki.moderation_proposals', (string) $this->t('Propostas'), $this->access->proposalsAccess($this->moderator)],
      'reports' => ['aculta_wiki.moderation_reports', (string) $this->t('Denúncias'), $this->access->reportsAccess($this->moderator)],
    ];
    $tabs = ['#type' => 'container', '#attributes' => ['class' => ['wiki-moderation-tabs']]];
    foreach ($routes as $key => [$route, $label, $access]) {
      if (!$access->isAllowed()) {
        continue;
      }
      $link = Link::fromTextAndUrl($label, Url::fromRoute($route))->toRenderable();
      if ($active === $key) {
        $attributes = $link['#attributes'] ?? NULL;
        $attributes = is_array($attributes) ? $attributes : [];
        $attributes['aria-current'] = 'page';
        $link['#attributes'] = $attributes;
      }
      $tabs[$key] = $link;
    }
    return [
      '#theme' => 'portal_component',
      '#name' => 'wiki-moderation-workspace',
      '#props' => [
        'title' => $title,
        'eyebrow' => 'WE SATIVA',
        'description' => $description,
      ],
      '#slots' => [
        'tabs' => $tabs,
        'content' => $content,
        'footer' => ['#plain_text' => (string) $this->t('O Fórum não concede acesso a esta fila. Decisões editoriais e denúncias possuem responsáveis distintos.')],
      ],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user', 'user.permissions', 'domain', 'url.query_args:page', 'url.query_args:status', 'url.query_args:wiki_proposal_cursor', 'url.query_args:wiki_revision_cursor'],
        'tags' => ['node_list:wiki_entry', 'aculta_wiki_proposal_list', 'aculta_contribution_list', 'aculta_cgu_report_list'],
      ],
    ];
  }

  private function requireAccess(AccessResultInterface $access): void {
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
  }

}
