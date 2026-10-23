<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\node\NodeInterface;

/**
 * Displays an author's private proposal and editorial feedback.
 */
final class WikiProposalStatusController extends ControllerBase {

  /**
   * Displays one proposal after route and entity access have been checked.
   */
  public function view(WikiProposal $proposal): array {
    $account = $this->currentUser();
    $access = $proposal->access('view', $account, TRUE);
    $cacheability = CacheableMetadata::createFromObject($proposal)
      ->addCacheableDependency($access)
      ->addCacheContexts(['domain', 'languages:language_interface']);

    $state = (string) $proposal->get('state')->value;
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'wiki-proposal-status'],
      'title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h1',
        '#value' => $this->t('Proposta: @subject', ['@subject' => $proposal->label()]),
      ],
      'state' => [
        '#type' => 'item',
        '#title' => $this->t('Situação'),
        '#plain_text' => (string) ($proposal::STATES[$state] ?? $state),
      ],
      'proposed_text' => [
        '#type' => 'container',
        'label' => ['#plain_text' => $this->t('Alteração sugerida')],
        'text' => ['#plain_text' => (string) $proposal->get('proposed_text')->value],
      ],
      'rationale' => [
        '#type' => 'container',
        'label' => ['#plain_text' => $this->t('Justificativa enviada')],
        'text' => ['#plain_text' => (string) $proposal->get('rationale')->value],
      ],
    ];

    $target = $proposal->get('target')->entity;
    if ($target instanceof NodeInterface) {
      $targetAccess = $target->access('view', $account, TRUE);
      $cacheability->addCacheableDependency($targetAccess);
      if ($targetAccess->isAllowed()) {
        $build['target'] = [
          '#type' => 'link',
          '#title' => $this->t('Abrir verbete relacionado: @title', ['@title' => $target->label()]),
          '#url' => Url::fromRoute('entity.node.canonical', ['node' => $target->id()]),
        ];
        $cacheability->addCacheableDependency($target);
      }
    }

    if (in_array($state, ['needs_changes', 'declined'], TRUE)) {
      $build['decision_note'] = [
        '#type' => 'container',
        'label' => ['#plain_text' => $this->t('Retorno editorial')],
        'text' => ['#plain_text' => (string) $proposal->get('decision_note')->value],
      ];
    }

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
      $build['sources'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Fontes enviadas'),
        '#items' => $sourceLinks,
      ];
    }

    $cacheability->applyTo($build);
    return $build;
  }

}
