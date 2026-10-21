<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Builds the work listings of an area's internal tabs (for example Revista: Todos, Rascunhos, Em revisão).
 *
 * The Studio stores nothing: it reads the owner's nodes with the entity query, accessCheck(TRUE), so each row
 * is still filtered by the node access of the owner. The listing carries the cacheability of the query.
 */
final class StudioListingBuilder {

  use StringTranslationTrait;

  private const LIMIT = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Table of nodes of a bundle in a moderation state (or any state when $state is NULL).
   *
   * @param array{bundle: string, state?: string|null} $listing
   */
  public function build(array $listing, AccountInterface $account): array {
    if (($listing['entity'] ?? 'node') === 'comment') {
      return $this->buildComments($listing);
    }
    $bundle = $listing['bundle'];
    $state = $listing['state'] ?? NULL;
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->sort('changed', 'DESC')
      ->range(0, self::LIMIT);
    // The editorial state lives in content_moderation_state (one entry per node), not in a node field.
    $stateStorage = $this->entityTypeManager->getStorage('content_moderation_state');
    $stateQuery = $stateStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('content_entity_type_id', 'node')
      ->condition('workflow', 'revista');
    if ($state !== NULL) {
      $stateQuery->condition('moderation_state', $state);
    }
    $stateEntities = $stateStorage->loadMultiple($stateQuery->execute());
    $stateByNode = [];
    foreach ($stateEntities as $entity) {
      $stateByNode[(int) $entity->get('content_entity_id')->value] = (string) $entity->get('moderation_state')->value;
    }
    if ($state !== NULL) {
      $query->condition('nid', array_keys($stateByNode) ?: [0], 'IN');
    }
    $nids = $query->execute();

    $rows = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      $rows[] = [
        'title' => ['data' => ['#type' => 'link', '#title' => $node->label(), '#url' => $node->toUrl()]],
        'state' => $stateByNode[(int) $node->id()] ?? '',
        'author' => $node->getOwner()?->getDisplayName() ?? '',
        'changed' => $this->dateFormatter->format($node->getChangedTime(), 'short'),
      ];
    }

    // A tabela rola dentro do próprio bloco no celular, sem rolagem horizontal da página.
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-listing-wrap', 'data-aculta-studio' => 'listing-wrap'],
      'table' => [
        '#type' => 'table',
      '#header' => [
        'title' => $this->t('Título'),
        'state' => $this->t('Situação'),
        'author' => $this->t('Autor'),
        'changed' => $this->t('Atualizado em'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum item nesta aba.'),
      '#attributes' => ['data-portal-ui' => 'studio-listing', 'data-aculta-studio' => 'listing'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list:' . $bundle],
      ],
    ];
    return $build;
  }


  /**
   * Table of comments of a bundle by status (0 = not published, e.g. awaiting moderation), with the node
   * they belong to. Comment access is checked per row through accessCheck(TRUE).
   *
   * @param array{entity: string, bundle: string, status: int} $listing
   */
  private function buildComments(array $listing): array {
    $storage = $this->entityTypeManager->getStorage('comment');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('comment_type', $listing['bundle'])
      ->condition('status', (int) $listing['status'])
      ->sort('created', 'DESC')
      ->range(0, self::LIMIT)
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $comment) {
      $node = $comment->getCommentedEntity();
      $rows[] = [
        'title' => ['data' => ['#type' => 'link', '#title' => $comment->label() ?: $this->t('(sem assunto)'), '#url' => $comment->toUrl()]],
        'state' => $this->t('Aguardando moderação'),
        'author' => $comment->getOwner()?->getDisplayName() ?? '',
        'changed' => $this->dateFormatter->format($comment->getChangedTime(), 'short'),
        'node' => $node ? ['data' => ['#type' => 'link', '#title' => $node->label(), '#url' => $node->toUrl()]] : '',
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-listing-wrap', 'data-aculta-studio' => 'listing-wrap'],
      'table' => [
        '#type' => 'table',
        '#header' => [
          'title' => $this->t('Comentário'),
          'state' => $this->t('Situação'),
          'author' => $this->t('Autor'),
          'changed' => $this->t('Atualizado em'),
          'node' => $this->t('Discussão'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum item nesta aba.'),
        '#attributes' => ['data-portal-ui' => 'studio-listing', 'data-aculta-studio' => 'listing'],
      ],
      '#cache' => ['contexts' => ['user.permissions'], 'tags' => ['comment_list']],
    ];
  }

}
