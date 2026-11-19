<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\aculta_user\CoAuthor\CoAuthorManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for co-authorship administration overview.
 */
final class CoAuthorAdminController extends ControllerBase {

  /**
   * The coauthor manager.
   *
   * @var \Drupal\aculta_user\CoAuthor\CoAuthorManagerInterface
   */
  protected $coAuthorManager;

  /**
   * Constructs a new CoAuthorAdminController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CoAuthorManagerInterface $coauthor_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->coAuthorManager = $coauthor_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_user.coauthor_manager'),
    );
  }

  /**
   * Renders paginated list of nodes with their primary author and coauthors.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return array<string, mixed>
   *   Render array representing the admin overview.
   */
  public function overview(Request $request): array {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $userStorage = $this->entityTypeManager->getStorage('user');

    $bundleFilter = trim((string) $request->query->get('bundle', ''));
    $titleFilter = trim((string) $request->query->get('title', ''));

    $query = $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_forum_topic', '<>')
      ->sort('nid', 'DESC')
      ->pager(25);

    if ($bundleFilter !== '') {
      $query->condition('type', $bundleFilter);
    }
    if ($titleFilter !== '') {
      $query->condition('title', '%' . $titleFilter . '%', 'LIKE');
    }

    $nids = $query->execute();

    $header = [
      $this->t('ID'),
      $this->t('Título'),
      $this->t('Tipo'),
      $this->t('Autor principal'),
      $this->t('Coautores'),
      $this->t('Ações'),
    ];

    $rows = [];
    if (!empty($nids)) {
      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $nodeStorage->loadMultiple($nids);
      foreach ($nodes as $node) {
        $owner = $node->getOwner();
        $ownerName = $owner->getDisplayName();

        $coauthorIds = $this->coAuthorManager->getCoAuthorIds($node);
        $coauthorNames = [];
        if (!empty($coauthorIds)) {
          /** @var \Drupal\user\UserInterface[] $coauthors */
          $coauthors = $userStorage->loadMultiple($coauthorIds);
          foreach ($coauthors as $coauthor) {
            $coauthorNames[] = $coauthor->getDisplayName();
          }
        }

        $manageUrl = Url::fromRoute('aculta_user.coauthors.manage', ['node' => $node->id()]);
        $actions = [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Gerenciar'),
            '#url' => $manageUrl,
            '#attributes' => ['class' => ['button', 'button--small']],
          ],
        ];

        $rows[] = [
          $node->id(),
          [
            'data' => [
              '#type' => 'link',
              '#title' => $node->label(),
              '#url' => $node->toUrl(),
            ],
          ],
          $node->bundle(),
          $ownerName,
          !empty($coauthorNames) ? implode(', ', $coauthorNames) : $this->t('Nenhum'),
          $actions,
        ];
      }
    }

    $build = [];

    // Filter form (simple GET parameters).
    $build['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filtrar conteúdos'),
      '#open' => $bundleFilter !== '' || $titleFilter !== '',
      'form' => [
        '#type' => 'form',
        '#method' => 'get',
        'title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Título'),
          '#default_value' => $titleFilter,
        ],
        'bundle' => [
          '#type' => 'select',
          '#title' => $this->t('Tipo de conteúdo'),
          '#options' => [
            '' => $this->t('- Todos -'),
            'revista' => $this->t('Revista'),
            'wiki_entry' => $this->t('Verbete'),
            'activity' => $this->t('Atividade'),
            'document' => $this->t('Documento'),
            'editorial_highlight' => $this->t('Destaque editorial'),
            'page' => $this->t('Página'),
          ],
          '#default_value' => $bundleFilter,
        ],
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Filtrar'),
        ],
      ],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum conteúdo encontrado para os filtros selecionados.'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
