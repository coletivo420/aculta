<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Displays an author's private proposal and editorial feedback.
 */
final class WikiProposalStatusController extends ControllerBase {

  private const HISTORY_PAGE_SIZE = 20;

  private const HISTORY_CANDIDATE_BATCH_SIZE = 50;

  private const HISTORY_SCAN_LIMIT = 250;

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly DateFormatterInterface $dateFormatter,
    AccountInterface $account,
  ) {
    $this->currentUser = $account;
  }

  /**
   * Displays one proposal after route and entity access have been checked.
   */
  public function view(WikiProposal $proposal, Request $request): array {
    $account = $this->currentUser;
    $access = $proposal->access('view', $account, TRUE);
    $cacheability = CacheableMetadata::createFromObject($proposal)
      ->addCacheableDependency($access)
      ->addCacheContexts(['domain', 'languages:language_interface']);

    $state = (string) $proposal->get('state')->value;
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'wiki-proposal-status'],
      'header' => [
        '#type' => 'component',
        '#component' => 'aculta420:context-header',
        '#props' => [
          'eyebrow' => (string) $this->t('WE SATIVA · Wiki420'),
          'title' => (string) $this->t('Proposta: @subject', ['@subject' => $proposal->label()]),
          'subtitle' => (string) $this->t('Acompanhe a análise editorial e o histórico da sua contribuição.'),
        ],
      ],
      'state' => $this->section($this->t('Situação'), [
        '#plain_text' => (string) ($proposal::STATES[$state] ?? $state),
      ]),
      'proposed_text' => $this->section($this->t('Alteração sugerida'), [
        '#type' => 'container',
        'text' => ['#plain_text' => (string) $proposal->get('proposed_text')->value],
      ]),
      'rationale' => $this->section($this->t('Justificativa enviada'), [
        '#type' => 'container',
        'text' => ['#plain_text' => (string) $proposal->get('rationale')->value],
      ]),
    ];

    $target = $proposal->get('target')->entity;
    if ($target instanceof NodeInterface) {
      $targetAccess = $target->access('view', $account, TRUE);
      $cacheability->addCacheableDependency($targetAccess);
      if ($targetAccess->isAllowed()) {
        $build['target'] = $this->section($this->t('Verbete relacionado'), [
          '#type' => 'link',
          '#title' => $this->t('Abrir verbete: @title', ['@title' => $target->label()]),
          '#url' => Url::fromRoute('entity.node.canonical', ['node' => $target->id()]),
        ]);
        $cacheability->addCacheableDependency($target);
      }
    }

    if (in_array($state, ['needs_changes', 'declined'], TRUE)) {
      $build['decision_note'] = $this->section($this->t('Retorno editorial'), [
        '#type' => 'container',
        'text' => ['#plain_text' => (string) $proposal->get('decision_note')->value],
      ]);
    }

    if ((int) $proposal->getOwnerId() === (int) $account->id()
      && in_array($state, ['submitted', 'needs_changes'], TRUE)) {
      $build['header']['#slots']['actions'] = [
        '#type' => 'link',
        '#title' => $this->t('Retirar proposta'),
        '#url' => Url::fromRoute('aculta_wiki.proposal_withdraw', ['proposal' => $proposal->id()]),
        '#attributes' => ['class' => ['button', 'button--secondary']],
      ];
    }

    $history = $this->proposalHistory($proposal, $account, $request);
    $build['history'] = $this->section($this->t('Histórico da proposta'), $history);
    $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($history));

    $sourceLinks = [];
    foreach ($proposal->get('source_uri') as $sourceItem) {
      $uri = (string) $sourceItem->value;
      if (!filter_var($uri, FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($uri, PHP_URL_SCHEME)), ['http', 'https'], TRUE)) {
        continue;
      }
      $sourceLinks[] = Link::fromTextAndUrl($uri, Url::fromUri($uri))->toRenderable();
    }
    if ($sourceLinks !== []) {
      $build['sources'] = $this->section($this->t('Fontes enviadas'), [
        '#theme' => 'item_list',
        '#items' => $sourceLinks,
      ]);
    }

    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Wraps a proposal status block in the shared content section component.
   *
   * @param string $title
   *   Accessible section heading.
   * @param array<string, mixed> $body
   *   Renderable section content.
   *
   * @return array<string, mixed>
   *   SDC render array.
   */
  private function section(\Stringable|string $title, array $body): array {
    return [
      '#type' => 'component',
      '#component' => 'aculta420:content-section',
      '#props' => ['title' => (string) $title],
      '#slots' => ['body' => $body],
    ];
  }

  /**
   * Builds a paginated, access-checked history without reviewer identities.
   *
   * @param \Drupal\aculta_wiki\Entity\WikiProposal $proposal
   *   Proposal whose revisions are shown.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account authorized to view the proposal.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request containing the history cursor.
   *
   * @return array
   *   A table of revisions visible to this account and an optional pager.
   */
  private function proposalHistory(WikiProposal $proposal, AccountInterface $account, Request $request): array {
    $metadata = (new CacheableMetadata())
      ->addCacheableDependency($proposal)
      ->addCacheContexts(['user', 'user.permissions', 'url.query_args:revision_cursor'])
      ->addCacheTags(['aculta_wiki_proposal_list']);
    $storage = $this->entities->getStorage('aculta_wiki_proposal');
    $cursor = $request->query->all()['revision_cursor'] ?? NULL;
    $scanCursor = is_string($cursor) && preg_match('/^[1-9][0-9]{0,12}$/D', $cursor)
      ? (int) $cursor
      : NULL;
    $candidateCount = 0;
    $hasMoreCandidates = FALSE;
    $hasExtraAuthorizedRevision = FALSE;
    $rows = [];

    while ($candidateCount < self::HISTORY_SCAN_LIMIT) {
      $batchLimit = min(
        self::HISTORY_CANDIDATE_BATCH_SIZE,
        self::HISTORY_SCAN_LIMIT - $candidateCount,
      );
      $query = $storage->getQuery()
        // Revision query discovers candidate IDs only. Each revision must
        // pass `view revision` below before any of its fields are rendered.
        ->allRevisions()
        ->accessCheck(FALSE)
        ->condition('id', $proposal->id())
        ->sort('revision_id', 'DESC')
        ->range(0, $batchLimit);
      if ($scanCursor !== NULL) {
        $query->condition('revision_id', $scanCursor, '<');
      }

      $queryRows = $query->execute();
      $revisionIds = array_map('strval', array_keys($queryRows));
      if ($revisionIds === []) {
        $hasMoreCandidates = FALSE;
        break;
      }
      $revisions = $storage->loadMultipleRevisions($revisionIds);
      $hasMoreCandidates = count($revisionIds) === $batchLimit;
      foreach ($revisionIds as $revisionId) {
        $scanCursor = (int) $revisionId;
        $candidateCount++;
        $revision = $revisions[$revisionId] ?? NULL;
        if (!$revision instanceof WikiProposal || (string) $revision->id() !== (string) $proposal->id()) {
          continue;
        }
        $revisionAccess = $revision->access('view revision', $account, TRUE);
        $metadata->addCacheableDependency($revisionAccess);
        if (!$revisionAccess->isAllowed()) {
          continue;
        }
        $metadata->addCacheableDependency($revision);
        $revisionState = (string) $revision->get('state')->value;
        $rows[] = [
          '_cursor' => (string) $revision->getRevisionId(),
          'updated' => $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short'),
          'state' => (string) ($revision::STATES[$revisionState] ?? $revisionState),
          'feedback' => in_array($revisionState, ['needs_changes', 'declined'], TRUE)
            ? (string) $revision->get('decision_note')->value
            : '',
        ];
        if (count($rows) > self::HISTORY_PAGE_SIZE) {
          $hasExtraAuthorizedRevision = TRUE;
          break 2;
        }
      }

      if (!$hasMoreCandidates) {
        break;
      }
    }

    if ($hasExtraAuthorizedRevision) {
      $nextCursor = $rows[self::HISTORY_PAGE_SIZE - 1]['_cursor'];
      $rows = array_slice($rows, 0, self::HISTORY_PAGE_SIZE);
    }
    elseif ($hasMoreCandidates && $scanCursor !== NULL) {
      $nextCursor = (string) $scanCursor;
    }
    else {
      $nextCursor = NULL;
    }
    $rows = array_map(static function (array $row): array {
      unset($row['_cursor']);
      return $row;
    }, $rows);

    $build = [
      '#type' => 'container',
      'table' => [
        '#type' => 'table',
        '#header' => [
          'updated' => $this->t('Atualizada em'),
          'state' => $this->t('Situação'),
          'feedback' => $this->t('Retorno editorial'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Ainda não há histórico da proposta.'),
      ],
      'pager' => $nextCursor !== NULL ? [
        '#type' => 'link',
        '#title' => $this->t('Ver revisões anteriores'),
        '#url' => Url::fromRoute('aculta_wiki.proposal_status', [
          'proposal' => $proposal->id(),
        ], ['query' => ['revision_cursor' => $nextCursor]]),
      ] : [],
    ];
    $metadata->applyTo($build);
    return $build;
  }

}
