<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administrative list of pinned Forum topics, with unpin actions (Discourse parity: fixados).
 */
final class PinnedTopicsAdminController extends ControllerBase {

  /**
   * Creates the controller with the date formatter.
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * The date formatter.
   */
  private DateFormatterInterface $dateFormatter;

  /**
   * Lists the pinned topics with title, category, author, date and an unpin action.
   *
   * @return array<string, mixed>
   *   Render array for the data-table component.
   */
  public function list(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'aculta_forum_topic')
      ->condition('sticky', 1)
      ->condition('status', 1)
      ->sort('changed', 'DESC')
      ->range(0, 100)
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $topic) {
      $category = '—';
      if ($topic->hasField('field_aculta_categories') && !$topic->get('field_aculta_categories')->isEmpty()) {
        $term = $topic->get('field_aculta_categories')->entity;
        $category = $term !== NULL ? (string) $term->label() : '—';
      }
      $owner = $topic->getOwner();
      $rows[] = [
        ['data' => ['#type' => 'link', '#title' => $topic->label(), '#url' => $topic->toUrl()]],
        $category,
        (string) $owner->getDisplayName(),
        $this->dateFormatter->format($topic->getCreatedTime(), 'custom', 'd/m/Y H:i'),
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Desafixar'),
            '#url' => Url::fromRoute('aculta_forum.topic_moderation_pin', ['node' => $topic->id()]),
          ],
        ],
      ];
    }

    $table = [
      '#type' => 'table',
      '#header' => [$this->t('Tópico'), $this->t('Categoria'), $this->t('Autor'), $this->t('Criado em'), $this->t('Ação')],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum tópico fixado.'),
    ];

    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:data-table',
      '#props' => ['label' => (string) $this->t('Tópicos fixados'), 'mobile_variant' => 'cards'],
      '#slots' => ['table' => $table],
      '#cache' => ['contexts' => ['user.permissions'], 'tags' => ['node_list']],
    ];
  }

}
