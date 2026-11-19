<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for coauthorship audit trail overview.
 */
final class CoAuthorAuditController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a CoAuthorAuditController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    DateFormatterInterface $date_formatter,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Displays the audit trail of coauthorship assignments.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return array<string, mixed>
   *   Render array representing the audit log.
   */
  public function listing(Request $request): array {
    $header = [
      $this->t('ID'),
      $this->t('Data/Hora'),
      $this->t('Conteúdo (NID)'),
      $this->t('Ator'),
      $this->t('Ação'),
      $this->t('Coautores resultantes'),
    ];

    $rows = [];
    if ($this->database->schema()->tableExists('aculta_coauthor_audit')) {
      $select = $this->database->select('aculta_coauthor_audit', 'a')
        ->fields('a')
        ->orderBy('id', 'DESC');
      /** @var \Drupal\Core\Database\Query\PagerSelectExtender $pager */
      $pager = $select->extend(PagerSelectExtender::class);
      $pager->limit(50);
      $results = $pager->execute();

      $userStorage = $this->entityTypeManager->getStorage('user');

      if ($results) {
        foreach ($results as $record) {
          /** @var object{id: int|string, created: int|string, nid: int|string, actor_uid: int|string, action: string, coauthors: string|null} $record */
          $actor = $userStorage->load((int) $record->actor_uid);
          $actorName = $actor instanceof UserInterface ? $actor->getDisplayName() : (string) $this->t('UID @uid', ['@uid' => (string) $record->actor_uid]);

          $coauthorNames = [];
          if (!empty($record->coauthors)) {
            $uids = array_filter(array_map('intval', explode(',', (string) $record->coauthors)));
            if (!empty($uids)) {
              /** @var \Drupal\user\UserInterface[] $loadedCoauthors */
              $loadedCoauthors = $userStorage->loadMultiple($uids);
              foreach ($loadedCoauthors as $coauthor) {
                $coauthorNames[] = $coauthor->getDisplayName();
              }
            }
          }

          $rows[] = [
            $record->id,
            $this->dateFormatter->format((int) $record->created, 'short'),
            $record->nid,
            $actorName,
            $record->action,
            !empty($coauthorNames) ? implode(', ', $coauthorNames) : $this->t('Nenhum'),
          ];
        }
      }
    }

    $build = [];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum registro de auditoria encontrado.'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
